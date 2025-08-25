<?php

if (!defined('ABSPATH')) {
    exit;
}

class AEF_API_Manager
{
    private $logger;
    private $settings;

    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->settings = get_option('aef_settings', []);
    }

    public function init()
    {
        add_action('wp_ajax_aef_test_api_call', [$this, 'ajax_test_api_call']);
        add_action('wp_ajax_aef_retry_failed_call', [$this, 'ajax_retry_failed_call']);
    }

    public function make_api_call($event_config, $event_data)
    {
        if (!$this->is_enabled()) {
            $this->logger->debug_log('API calls are disabled');
            return false;
        }

        $log_id = $this->logger->log_api_call(
            $event_config->id,
            $event_config->event_name,
            $event_config->api_endpoint,
            $event_data
        );

        if (!$log_id) {
            $this->logger->debug_log('Failed to create log entry');
            return false;
        }

        $payload = $this->build_payload($event_config->payload_template, $event_data);
        $headers = $this->build_headers($event_config->headers);

        $args = [
            'method' => $event_config->http_method,
            'headers' => $headers,
            'body' => $payload,
            'timeout' => $this->get_timeout(),
            'blocking' => false
        ];

        if ($event_config->http_method === 'GET') {
            unset($args['body']);
            if (!empty($payload)) {
                $event_config->api_endpoint = add_query_arg($payload, $event_config->api_endpoint);
            }
        }

        $args = apply_filters('aef_api_request_args', $args, $event_config, $event_data);

        wp_remote_post($event_config->api_endpoint, array_merge($args, [
            'blocking' => true
        ]));

        $this->schedule_async_api_call($log_id, $event_config, $event_data, $args);

        return $log_id;
    }

    private function schedule_async_api_call($log_id, $event_config, $event_data, $args)
    {
        wp_schedule_single_event(time() + 5, 'aef_process_api_call', [
            $log_id,
            $event_config,
            $event_data,
            $args
        ]);
    }

    public function process_api_call($log_id, $event_config, $event_data, $args)
    {
        add_action('aef_process_api_call', function($log_id, $event_config, $event_data, $args) {
            $response = wp_remote_post($event_config->api_endpoint, $args);

            if (is_wp_error($response)) {
                $this->handle_api_error($log_id, $event_config, $response->get_error_message());
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code >= 200 && $response_code < 300) {
                $this->logger->update_log_entry($log_id, $response_code, $response_body, 'success');
                do_action('aef_api_call_success', $event_config, $event_data, $response);
            } else {
                $this->handle_api_error($log_id, $event_config, "HTTP {$response_code}: {$response_body}");
                do_action('aef_api_call_failed', $event_config, $event_data, $response);
            }
        }, 10, 4);
    }

    private function handle_api_error($log_id, $event_config, $error_message)
    {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'aef_logs';
        $current_log = $wpdb->get_row(
            $wpdb->prepare("SELECT attempt_count FROM {$logs_table} WHERE id = %d", $log_id)
        );

        $attempt_count = $current_log ? $current_log->attempt_count + 1 : 1;
        $max_attempts = $event_config->retry_attempts ?: $this->get_max_retry_attempts();

        $status = ($attempt_count >= $max_attempts) ? 'failed' : 'retry_pending';

        $this->logger->update_log_entry(
            $log_id,
            null,
            null,
            $status,
            $error_message,
            $attempt_count
        );

        if ($status === 'retry_pending') {
            $retry_delay = $this->get_retry_delay();
            wp_schedule_single_event(
                time() + $retry_delay,
                'aef_retry_single_call',
                [$log_id, $event_config]
            );
        }
    }

    public function retry_api_call($log_id, $event_config, $request_data)
    {
        $payload = is_string($request_data) ? $request_data : wp_json_encode($request_data);
        $headers = $this->build_headers($event_config->headers);

        $args = [
            'method' => $event_config->http_method,
            'headers' => $headers,
            'body' => $payload,
            'timeout' => $this->get_timeout(),
            'blocking' => true
        ];

        if ($event_config->http_method === 'GET') {
            unset($args['body']);
            if (!empty($request_data)) {
                $event_config->api_endpoint = add_query_arg($request_data, $event_config->api_endpoint);
            }
        }

        $response = wp_remote_post($event_config->api_endpoint, $args);

        if (is_wp_error($response)) {
            $this->handle_api_error($log_id, $event_config, $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 200 && $response_code < 300) {
            $this->logger->update_log_entry($log_id, $response_code, $response_body, 'success');
            return true;
        } else {
            $this->handle_api_error($log_id, $event_config, "HTTP {$response_code}: {$response_body}");
            return false;
        }
    }

    private function build_payload($template, $event_data)
    {
        if (empty($template)) {
            return wp_json_encode($event_data);
        }

        $payload = $this->replace_template_variables($template, $event_data);
        
        $decoded = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $payload;
        }

        return wp_json_encode(['data' => $payload]);
    }

    private function build_headers($headers_config)
    {
        $default_headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'API-Event-Framework/' . AEF_VERSION . '; ' . home_url()
        ];

        if (empty($headers_config)) {
            return $default_headers;
        }

        $custom_headers = json_decode($headers_config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default_headers;
        }

        return array_merge($default_headers, $custom_headers);
    }

    private function replace_template_variables($template, $data)
    {
        $variables = $this->extract_template_variables($data);
        
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, $value, $template);
        }

        $template = str_replace('{{timestamp}}', current_time('mysql'), $template);
        $template = str_replace('{{site_url}}', home_url(), $template);
        $template = str_replace('{{site_name}}', get_bloginfo('name'), $template);

        return $template;
    }

    private function extract_template_variables($data, $prefix = '')
    {
        $variables = [];

        foreach ($data as $key => $value) {
            $variable_key = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value) || is_object($value)) {
                $variables = array_merge($variables, $this->extract_template_variables($value, $variable_key));
            } else {
                $variables[$variable_key] = $value;
            }
        }

        return $variables;
    }

    public function ajax_test_api_call()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'api-event-framework'));
        }

        check_ajax_referer('aef_admin_nonce', 'nonce');

        $endpoint = sanitize_url($_POST['endpoint']);
        $method = sanitize_text_field($_POST['method']);
        $headers = sanitize_textarea_field($_POST['headers']);
        $payload = sanitize_textarea_field($_POST['payload']);

        if (empty($endpoint)) {
            wp_send_json_error(__('API endpoint is required', 'api-event-framework'));
        }

        $test_data = [
            'test_call' => true,
            'timestamp' => current_time('mysql'),
            'user_id' => 1,
            'user_email' => 'test@example.com'
        ];

        $processed_payload = $this->build_payload($payload, $test_data);
        $processed_headers = $this->build_headers($headers);

        $args = [
            'method' => strtoupper($method),
            'headers' => $processed_headers,
            'body' => $processed_payload,
            'timeout' => $this->get_timeout(),
            'blocking' => true
        ];

        if (strtoupper($method) === 'GET') {
            unset($args['body']);
        }

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        wp_send_json_success([
            'response_code' => $response_code,
            'response_body' => $response_body,
            'sent_data' => $processed_payload
        ]);
    }

    public function ajax_retry_failed_call()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'api-event-framework'));
        }

        check_ajax_referer('aef_admin_nonce', 'nonce');

        $log_id = intval($_POST['log_id']);
        
        global $wpdb;
        $logs_table = $wpdb->prefix . 'aef_logs';
        $events_table = $wpdb->prefix . 'aef_events';

        $log = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$logs_table} WHERE id = %d", $log_id)
        );

        if (!$log) {
            wp_send_json_error(__('Log entry not found', 'api-event-framework'));
        }

        $event_config = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$events_table} WHERE id = %d", $log->event_id)
        );

        if (!$event_config) {
            wp_send_json_error(__('Event configuration not found', 'api-event-framework'));
        }

        $request_data = json_decode($log->request_data, true);
        $success = $this->retry_api_call($log_id, $event_config, $request_data);

        if ($success) {
            wp_send_json_success(__('API call retried successfully', 'api-event-framework'));
        } else {
            wp_send_json_error(__('Failed to retry API call', 'api-event-framework'));
        }
    }

    private function is_enabled()
    {
        return isset($this->settings['enabled']) ? $this->settings['enabled'] : true;
    }

    private function get_timeout()
    {
        return isset($this->settings['timeout']) ? intval($this->settings['timeout']) : 30;
    }

    private function get_retry_delay()
    {
        return isset($this->settings['retry_delay']) ? intval($this->settings['retry_delay']) : 300;
    }

    private function get_max_retry_attempts()
    {
        return isset($this->settings['max_retry_attempts']) ? intval($this->settings['max_retry_attempts']) : 3;
    }
}