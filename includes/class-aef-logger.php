<?php

if (!defined('ABSPATH')) {
    exit;
}

class AEF_Logger
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aef_logs';
    }

    public function log_api_call($event_id, $event_name, $api_endpoint, $request_data, $response_code = null, $response_body = null, $status = 'pending', $error_message = null)
    {
        global $wpdb;

        $data = [
            'event_id' => $event_id,
            'event_name' => $event_name,
            'api_endpoint' => $api_endpoint,
            'request_data' => wp_json_encode($request_data),
            'response_code' => $response_code,
            'response_body' => $response_body,
            'status' => $status,
            'error_message' => $error_message,
            'attempt_count' => 1,
            'created_at' => current_time('mysql')
        ];

        $format = ['%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s'];

        $result = $wpdb->insert($this->table_name, $data, $format);

        if ($result === false) {
            error_log('AEF Logger: Failed to insert log entry - ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    public function update_log_entry($log_id, $response_code, $response_body, $status, $error_message = null, $attempt_count = null)
    {
        global $wpdb;

        $data = [
            'response_code' => $response_code,
            'response_body' => $response_body,
            'status' => $status
        ];

        $format = ['%d', '%s', '%s'];

        if ($error_message !== null) {
            $data['error_message'] = $error_message;
            $format[] = '%s';
        }

        if ($attempt_count !== null) {
            $data['attempt_count'] = $attempt_count;
            $format[] = '%d';
        }

        return $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $log_id],
            $format,
            ['%d']
        );
    }

    public function get_logs($limit = 50, $offset = 0, $status = null, $event_name = null)
    {
        global $wpdb;

        $where_conditions = [];
        $where_values = [];

        if ($status) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status;
        }

        if ($event_name) {
            $where_conditions[] = 'event_name = %s';
            $where_values[] = $event_name;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return $wpdb->get_results($query);
    }

    public function get_failed_logs($limit = 10)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE status = 'failed' ORDER BY created_at DESC LIMIT %d",
            $limit
        );

        return $wpdb->get_results($query);
    }

    public function cleanup_old_logs($days = 30)
    {
        global $wpdb;

        $date_threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date_threshold
            )
        );

        if ($result !== false) {
            return $result;
        }

        error_log('AEF Logger: Failed to cleanup old logs - ' . $wpdb->last_error);
        return false;
    }

    public function get_stats()
    {
        global $wpdb;

        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_calls,
                COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_calls,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_calls,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_calls,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as calls_24h
            FROM {$this->table_name}",
            ARRAY_A
        );

        return $stats;
    }

    public function debug_log($message, $data = null)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = 'AEF Debug: ' . $message;
            if ($data !== null) {
                $log_message .= ' | Data: ' . wp_json_encode($data);
            }
            error_log($log_message);
        }
    }
}