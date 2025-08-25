<?php

if (!defined('ABSPATH')) {
    exit;
}

class AEF_Event_Registry
{
    private $logger;
    private $table_name;
    private $registered_events = [];
    private $default_events = [
        'user_register' => [
            'name' => 'User Registration',
            'description' => 'Triggered when a new user registers',
            'data_context' => ['user_id', 'user_email', 'user_login', 'user_data']
        ],
        'wp_login' => [
            'name' => 'User Login',
            'description' => 'Triggered when a user logs in',
            'data_context' => ['user_login', 'user_id', 'user_email', 'login_time']
        ],
        'wp_logout' => [
            'name' => 'User Logout',
            'description' => 'Triggered when a user logs out',
            'data_context' => ['user_id', 'user_email', 'logout_time']
        ],
        'profile_update' => [
            'name' => 'Profile Update',
            'description' => 'Triggered when a user updates their profile',
            'data_context' => ['user_id', 'user_email', 'updated_fields', 'old_data', 'new_data']
        ],
        'publish_post' => [
            'name' => 'Post Published',
            'description' => 'Triggered when a post is published',
            'data_context' => ['post_id', 'post_title', 'post_author', 'post_content', 'post_type']
        ],
        'wp_insert_comment' => [
            'name' => 'Comment Added',
            'description' => 'Triggered when a new comment is added',
            'data_context' => ['comment_id', 'comment_author', 'comment_content', 'post_id']
        ]
    ];

    public function __construct($logger)
    {
        $this->logger = $logger;
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aef_events';
    }

    public function init()
    {
        $this->register_wordpress_hooks();
        add_action('aef_retry_failed_calls', [$this, 'retry_failed_api_calls']);
        
        if (!wp_next_scheduled('aef_retry_failed_calls')) {
            wp_schedule_event(time(), 'hourly', 'aef_retry_failed_calls');
        }
    }

    private function register_wordpress_hooks()
    {
        add_action('user_register', [$this, 'handle_user_register']);
        add_action('wp_login', [$this, 'handle_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'handle_user_logout']);
        add_action('profile_update', [$this, 'handle_profile_update'], 10, 2);
        add_action('publish_post', [$this, 'handle_post_publish']);
        add_action('wp_insert_comment', [$this, 'handle_comment_insert'], 10, 2);
        
        do_action('aef_register_custom_events', $this);
    }

    public function handle_user_register($user_id)
    {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $event_data = [
            'user_id' => $user_id,
            'user_email' => $user->user_email,
            'user_login' => $user->user_login,
            'user_data' => [
                'display_name' => $user->display_name,
                'first_name' => get_user_meta($user_id, 'first_name', true),
                'last_name' => get_user_meta($user_id, 'last_name', true),
                'registered_date' => $user->user_registered
            ]
        ];

        $this->trigger_event('user_register', $event_data);
    }

    public function handle_user_login($user_login, $user)
    {
        $event_data = [
            'user_login' => $user_login,
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'login_time' => current_time('mysql'),
            'user_roles' => $user->roles
        ];

        $this->trigger_event('wp_login', $event_data);
    }

    public function handle_user_logout()
    {
        $user = wp_get_current_user();
        if ($user->exists()) {
            $event_data = [
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
                'logout_time' => current_time('mysql')
            ];

            $this->trigger_event('wp_logout', $event_data);
        }
    }

    public function handle_profile_update($user_id, $old_user_data)
    {
        $new_user_data = get_user_by('id', $user_id);
        
        $event_data = [
            'user_id' => $user_id,
            'user_email' => $new_user_data->user_email,
            'updated_fields' => $this->get_updated_fields($old_user_data, $new_user_data),
            'old_data' => $this->sanitize_user_data($old_user_data),
            'new_data' => $this->sanitize_user_data($new_user_data)
        ];

        $this->trigger_event('profile_update', $event_data);
    }

    public function handle_post_publish($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return;
        }

        $author = get_user_by('id', $post->post_author);
        
        $event_data = [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_author' => $author ? $author->display_name : '',
            'post_author_email' => $author ? $author->user_email : '',
            'post_content' => wp_trim_words($post->post_content, 50),
            'post_type' => $post->post_type,
            'post_date' => $post->post_date,
            'post_url' => get_permalink($post_id)
        ];

        $this->trigger_event('publish_post', $event_data);
    }

    public function handle_comment_insert($comment_id, $comment_approved)
    {
        if ($comment_approved !== 1) {
            return;
        }

        $comment = get_comment($comment_id);
        if (!$comment) {
            return;
        }

        $event_data = [
            'comment_id' => $comment_id,
            'comment_author' => $comment->comment_author,
            'comment_author_email' => $comment->comment_author_email,
            'comment_content' => $comment->comment_content,
            'post_id' => $comment->comment_post_ID,
            'post_title' => get_the_title($comment->comment_post_ID),
            'comment_date' => $comment->comment_date
        ];

        $this->trigger_event('wp_insert_comment', $event_data);
    }

    public function trigger_event($event_name, $event_data)
    {
        $this->logger->debug_log("Event triggered: {$event_name}", $event_data);

        $configured_events = $this->get_configured_events($event_name);
        
        if (empty($configured_events)) {
            $this->logger->debug_log("No configured API calls for event: {$event_name}");
            return;
        }

        foreach ($configured_events as $event_config) {
            if (!$event_config->is_active) {
                continue;
            }

            $api_manager = new AEF_API_Manager($this->logger);
            $api_manager->make_api_call($event_config, $event_data);
        }

        do_action('aef_event_triggered', $event_name, $event_data);
    }

    public function register_custom_event($event_name, $event_info)
    {
        $this->registered_events[$event_name] = array_merge([
            'name' => ucwords(str_replace('_', ' ', $event_name)),
            'description' => '',
            'data_context' => []
        ], $event_info);

        return true;
    }

    public function get_available_events()
    {
        return array_merge($this->default_events, $this->registered_events);
    }

    public function get_configured_events($event_name = null)
    {
        global $wpdb;

        $where_clause = '';
        if ($event_name) {
            $where_clause = $wpdb->prepare('WHERE event_name = %s', $event_name);
        }

        $query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY id ASC";
        
        return $wpdb->get_results($query);
    }

    public function save_event_configuration($event_name, $api_endpoint, $http_method = 'POST', $headers = [], $payload_template = '', $is_active = true, $retry_attempts = 3)
    {
        global $wpdb;

        $data = [
            'event_name' => $event_name,
            'api_endpoint' => $api_endpoint,
            'http_method' => strtoupper($http_method),
            'headers' => wp_json_encode($headers),
            'payload_template' => $payload_template,
            'is_active' => $is_active ? 1 : 0,
            'retry_attempts' => intval($retry_attempts),
            'updated_at' => current_time('mysql')
        ];

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT id FROM {$this->table_name} WHERE event_name = %s", $event_name)
        );

        if ($existing) {
            $result = $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $existing->id],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'],
                ['%d']
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );
        }

        return $result !== false;
    }

    private function get_updated_fields($old_data, $new_data)
    {
        $updated_fields = [];
        $fields_to_check = ['user_email', 'user_login', 'display_name', 'user_url'];

        foreach ($fields_to_check as $field) {
            if (isset($old_data->$field) && isset($new_data->$field) && $old_data->$field !== $new_data->$field) {
                $updated_fields[] = $field;
            }
        }

        return $updated_fields;
    }

    private function sanitize_user_data($user_data)
    {
        return [
            'user_email' => $user_data->user_email,
            'user_login' => $user_data->user_login,
            'display_name' => $user_data->display_name,
            'user_url' => $user_data->user_url
        ];
    }

    public function retry_failed_api_calls()
    {
        $failed_logs = $this->logger->get_failed_logs(10);
        
        if (empty($failed_logs)) {
            return;
        }

        $api_manager = new AEF_API_Manager($this->logger);
        
        foreach ($failed_logs as $log) {
            $event_config = $this->get_event_config_by_id($log->event_id);
            if (!$event_config || $log->attempt_count >= $event_config->retry_attempts) {
                continue;
            }

            $request_data = json_decode($log->request_data, true);
            $api_manager->retry_api_call($log->id, $event_config, $request_data);
        }
    }

    private function get_event_config_by_id($event_id)
    {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $event_id)
        );
    }
}