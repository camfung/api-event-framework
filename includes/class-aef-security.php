<?php

if (!defined('ABSPATH')) {
    exit;
}

class AEF_Security
{
    public static function sanitize_event_config($data)
    {
        return [
            'event_name' => sanitize_text_field($data['event_name'] ?? ''),
            'api_endpoint' => sanitize_url($data['api_endpoint'] ?? ''),
            'http_method' => in_array(strtoupper($data['http_method'] ?? 'POST'), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']) 
                ? strtoupper($data['http_method']) 
                : 'POST',
            'headers' => self::sanitize_json_field($data['headers'] ?? '{}'),
            'payload_template' => sanitize_textarea_field($data['payload_template'] ?? ''),
            'is_active' => !empty($data['is_active']),
            'retry_attempts' => max(0, min(10, intval($data['retry_attempts'] ?? 3)))
        ];
    }

    public static function sanitize_json_field($json_string)
    {
        $json_string = sanitize_textarea_field($json_string);
        
        $decoded = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '{}';
        }

        return wp_json_encode($decoded);
    }

    public static function validate_api_endpoint($endpoint)
    {
        if (empty($endpoint)) {
            return new WP_Error('empty_endpoint', __('API endpoint cannot be empty', 'api-event-framework'));
        }

        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', __('Invalid URL format', 'api-event-framework'));
        }

        $parsed_url = parse_url($endpoint);
        if (!in_array($parsed_url['scheme'], ['http', 'https'])) {
            return new WP_Error('invalid_scheme', __('Only HTTP and HTTPS protocols are allowed', 'api-event-framework'));
        }

        $blocked_hosts = apply_filters('aef_blocked_hosts', [
            'localhost',
            '127.0.0.1',
            '0.0.0.0'
        ]);

        if (in_array($parsed_url['host'], $blocked_hosts)) {
            return new WP_Error('blocked_host', __('This host is not allowed', 'api-event-framework'));
        }

        if (isset($parsed_url['port']) && !self::is_allowed_port($parsed_url['port'])) {
            return new WP_Error('blocked_port', __('This port is not allowed', 'api-event-framework'));
        }

        return true;
    }

    public static function validate_payload_template($template)
    {
        if (empty($template)) {
            return true;
        }

        if (preg_match('/\{\{.*?(system|exec|eval|shell|file|include).*?\}\}/', $template)) {
            return new WP_Error('dangerous_template', __('Template contains potentially dangerous variables', 'api-event-framework'));
        }

        $decoded = json_decode($template);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE && !is_string($template)) {
            return new WP_Error('invalid_json', __('Template must be valid JSON or plain text', 'api-event-framework'));
        }

        return true;
    }

    public static function validate_headers($headers_json)
    {
        if (empty($headers_json)) {
            return true;
        }

        $headers = json_decode($headers_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Headers must be valid JSON', 'api-event-framework'));
        }

        if (!is_array($headers)) {
            return new WP_Error('invalid_format', __('Headers must be a JSON object', 'api-event-framework'));
        }

        $dangerous_headers = [
            'authorization',
            'cookie',
            'set-cookie',
            'x-forwarded-for',
            'x-real-ip'
        ];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $dangerous_headers)) {
                if (!apply_filters('aef_allow_sensitive_header', false, $name, $value)) {
                    return new WP_Error('dangerous_header', 
                        sprintf(__('Header "%s" is not allowed for security reasons', 'api-event-framework'), $name));
                }
            }

            if (strlen($name) > 100 || strlen($value) > 1000) {
                return new WP_Error('header_too_long', __('Header name or value is too long', 'api-event-framework'));
            }
        }

        return true;
    }

    public static function check_rate_limit($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $transient_key = 'aef_rate_limit_' . $user_id;
        $current_count = get_transient($transient_key);

        $rate_limit = apply_filters('aef_rate_limit_per_hour', 100, $user_id);

        if ($current_count === false) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }

        if ($current_count >= $rate_limit) {
            return new WP_Error('rate_limit_exceeded', 
                sprintf(__('Rate limit exceeded. Maximum %d API calls per hour', 'api-event-framework'), $rate_limit));
        }

        set_transient($transient_key, $current_count + 1, HOUR_IN_SECONDS);
        return true;
    }

    public static function mask_sensitive_data($data)
    {
        $sensitive_keys = [
            'password',
            'api_key',
            'secret',
            'token',
            'auth',
            'authorization',
            'key',
            'private'
        ];

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = self::mask_sensitive_data($value);
                } else {
                    foreach ($sensitive_keys as $sensitive) {
                        if (stripos($key, $sensitive) !== false) {
                            $data[$key] = '***MASKED***';
                            break;
                        }
                    }
                }
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data->$key = self::mask_sensitive_data($value);
                } else {
                    foreach ($sensitive_keys as $sensitive) {
                        if (stripos($key, $sensitive) !== false) {
                            $data->$key = '***MASKED***';
                            break;
                        }
                    }
                }
            }
        }

        return $data;
    }

    public static function log_security_event($event_type, $details = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'AEF Security Event: %s | User: %d | IP: %s | Details: %s',
                $event_type,
                get_current_user_id(),
                self::get_client_ip(),
                wp_json_encode($details)
            ));
        }

        do_action('aef_security_event', $event_type, $details);
    }

    public static function get_client_ip()
    {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private static function is_allowed_port($port)
    {
        $blocked_ports = apply_filters('aef_blocked_ports', [
            22,    // SSH
            23,    // Telnet
            25,    // SMTP
            53,    // DNS
            110,   // POP3
            143,   // IMAP
            993,   // IMAPS
            995,   // POP3S
            1433,  // SQL Server
            3306,  // MySQL
            5432,  // PostgreSQL
            6379,  // Redis
            27017  // MongoDB
        ]);

        return !in_array($port, $blocked_ports);
    }

    public static function encrypt_sensitive_data($data, $key = null)
    {
        if (!$key) {
            $key = defined('AUTH_KEY') ? AUTH_KEY : 'aef-default-key';
        }

        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function decrypt_sensitive_data($data, $key = null)
    {
        if (!$key) {
            $key = defined('AUTH_KEY') ? AUTH_KEY : 'aef-default-key';
        }

        $data = base64_decode($data);
        list($encrypted_data, $iv) = explode('::', $data, 2);
        
        return openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
    }
}