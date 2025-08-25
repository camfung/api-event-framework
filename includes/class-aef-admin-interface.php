<?php

if (!defined('ABSPATH')) {
    exit;
}

class AEF_Admin_Interface
{
    private $event_registry;
    private $logger;

    public function init()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_aef_save_event', [$this, 'ajax_save_event']);
        add_action('wp_ajax_aef_delete_event', [$this, 'ajax_delete_event']);
        add_action('wp_ajax_aef_toggle_event', [$this, 'ajax_toggle_event']);
        add_action('wp_ajax_aef_get_logs', [$this, 'ajax_get_logs']);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('API Event Framework', 'api-event-framework'),
            __('API Events', 'api-event-framework'),
            'manage_options',
            'aef-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-admin-plugins',
            30
        );

        add_submenu_page(
            'aef-dashboard',
            __('Dashboard', 'api-event-framework'),
            __('Dashboard', 'api-event-framework'),
            'manage_options',
            'aef-dashboard',
            [$this, 'dashboard_page']
        );

        add_submenu_page(
            'aef-dashboard',
            __('Event Configuration', 'api-event-framework'),
            __('Events', 'api-event-framework'),
            'manage_options',
            'aef-events',
            [$this, 'events_page']
        );

        add_submenu_page(
            'aef-dashboard',
            __('API Logs', 'api-event-framework'),
            __('Logs', 'api-event-framework'),
            'manage_options',
            'aef-logs',
            [$this, 'logs_page']
        );

        add_submenu_page(
            'aef-dashboard',
            __('Settings', 'api-event-framework'),
            __('Settings', 'api-event-framework'),
            'manage_options',
            'aef-settings',
            [$this, 'settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('aef_settings_group', 'aef_settings', [$this, 'sanitize_settings']);
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, 'aef-') === false) {
            return;
        }

        wp_enqueue_script(
            'aef-admin',
            AEF_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            AEF_VERSION,
            true
        );

        wp_enqueue_style(
            'aef-admin',
            AEF_PLUGIN_URL . 'assets/admin.css',
            [],
            AEF_VERSION
        );

        wp_localize_script('aef-admin', 'aef_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aef_admin_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this event configuration?', 'api-event-framework'),
                'test_success' => __('API test successful!', 'api-event-framework'),
                'test_failed' => __('API test failed:', 'api-event-framework'),
                'loading' => __('Loading...', 'api-event-framework'),
                'saved' => __('Settings saved successfully!', 'api-event-framework')
            ]
        ]);
    }

    public function dashboard_page()
    {
        $this->logger = new AEF_Logger();
        $stats = $this->logger->get_stats();
        $recent_logs = $this->logger->get_logs(10);
        
        ?>
        <div class="wrap">
            <h1><?php _e('API Event Framework Dashboard', 'api-event-framework'); ?></h1>
            
            <div class="aef-dashboard">
                <div class="aef-stats-grid">
                    <div class="aef-stat-card">
                        <h3><?php _e('Total API Calls', 'api-event-framework'); ?></h3>
                        <p class="aef-stat-number"><?php echo number_format($stats['total_calls']); ?></p>
                    </div>
                    <div class="aef-stat-card success">
                        <h3><?php _e('Successful', 'api-event-framework'); ?></h3>
                        <p class="aef-stat-number"><?php echo number_format($stats['successful_calls']); ?></p>
                    </div>
                    <div class="aef-stat-card error">
                        <h3><?php _e('Failed', 'api-event-framework'); ?></h3>
                        <p class="aef-stat-number"><?php echo number_format($stats['failed_calls']); ?></p>
                    </div>
                    <div class="aef-stat-card pending">
                        <h3><?php _e('Last 24 Hours', 'api-event-framework'); ?></h3>
                        <p class="aef-stat-number"><?php echo number_format($stats['calls_24h']); ?></p>
                    </div>
                </div>

                <div class="aef-recent-activity">
                    <h2><?php _e('Recent Activity', 'api-event-framework'); ?></h2>
                    <?php if (empty($recent_logs)): ?>
                        <p><?php _e('No recent activity found.', 'api-event-framework'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Event', 'api-event-framework'); ?></th>
                                    <th><?php _e('Endpoint', 'api-event-framework'); ?></th>
                                    <th><?php _e('Status', 'api-event-framework'); ?></th>
                                    <th><?php _e('Date', 'api-event-framework'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log->event_name); ?></td>
                                    <td><?php echo esc_html(substr($log->api_endpoint, 0, 50)) . (strlen($log->api_endpoint) > 50 ? '...' : ''); ?></td>
                                    <td><span class="aef-status aef-status-<?php echo esc_attr($log->status); ?>"><?php echo esc_html(ucfirst($log->status)); ?></span></td>
                                    <td><?php echo esc_html($log->created_at); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function events_page()
    {
        $this->event_registry = new AEF_Event_Registry(new AEF_Logger());
        $available_events = $this->event_registry->get_available_events();
        $configured_events = $this->event_registry->get_configured_events();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Event Configuration', 'api-event-framework'); ?></h1>
            
            <div class="aef-events-page">
                <div class="aef-add-event">
                    <h2><?php _e('Add New Event Configuration', 'api-event-framework'); ?></h2>
                    <form id="aef-event-form" class="aef-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="event_name"><?php _e('Event Type', 'api-event-framework'); ?></label></th>
                                <td>
                                    <select id="event_name" name="event_name" required>
                                        <option value=""><?php _e('Select an event...', 'api-event-framework'); ?></option>
                                        <?php foreach ($available_events as $event_key => $event_info): ?>
                                            <option value="<?php echo esc_attr($event_key); ?>" 
                                                    data-context="<?php echo esc_attr(wp_json_encode($event_info['data_context'])); ?>">
                                                <?php echo esc_html($event_info['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" id="event-description"></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="api_endpoint"><?php _e('API Endpoint', 'api-event-framework'); ?></label></th>
                                <td>
                                    <input type="url" id="api_endpoint" name="api_endpoint" class="regular-text" required>
                                    <p class="description"><?php _e('The full URL to your API endpoint', 'api-event-framework'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="http_method"><?php _e('HTTP Method', 'api-event-framework'); ?></label></th>
                                <td>
                                    <select id="http_method" name="http_method">
                                        <option value="POST">POST</option>
                                        <option value="GET">GET</option>
                                        <option value="PUT">PUT</option>
                                        <option value="PATCH">PATCH</option>
                                        <option value="DELETE">DELETE</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="headers"><?php _e('Headers (JSON)', 'api-event-framework'); ?></label></th>
                                <td>
                                    <textarea id="headers" name="headers" class="large-text" rows="4" placeholder='{"Authorization": "Bearer YOUR_TOKEN", "Custom-Header": "value"}'></textarea>
                                    <p class="description"><?php _e('Additional headers as JSON object', 'api-event-framework'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="payload_template"><?php _e('Payload Template', 'api-event-framework'); ?></label></th>
                                <td>
                                    <textarea id="payload_template" name="payload_template" class="large-text" rows="8" 
                                              placeholder='{"user_id": "{{user_id}}", "email": "{{user_email}}", "timestamp": "{{timestamp}}"}'></textarea>
                                    <p class="description"><?php _e('JSON template with variables like {{user_id}}, {{user_email}}, etc.', 'api-event-framework'); ?></p>
                                    <div id="available-variables" style="margin-top: 10px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="retry_attempts"><?php _e('Retry Attempts', 'api-event-framework'); ?></label></th>
                                <td>
                                    <input type="number" id="retry_attempts" name="retry_attempts" min="0" max="10" value="3">
                                    <p class="description"><?php _e('Number of retry attempts for failed calls', 'api-event-framework'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="is_active"><?php _e('Status', 'api-event-framework'); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                                        <?php _e('Active', 'api-event-framework'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="aef-form-actions">
                            <button type="submit" class="button button-primary"><?php _e('Save Configuration', 'api-event-framework'); ?></button>
                            <button type="button" id="test-api-call" class="button button-secondary"><?php _e('Test API Call', 'api-event-framework'); ?></button>
                        </div>
                    </form>
                </div>

                <div class="aef-configured-events">
                    <h2><?php _e('Configured Events', 'api-event-framework'); ?></h2>
                    <?php if (empty($configured_events)): ?>
                        <p><?php _e('No events configured yet.', 'api-event-framework'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Event', 'api-event-framework'); ?></th>
                                    <th><?php _e('Endpoint', 'api-event-framework'); ?></th>
                                    <th><?php _e('Method', 'api-event-framework'); ?></th>
                                    <th><?php _e('Status', 'api-event-framework'); ?></th>
                                    <th><?php _e('Actions', 'api-event-framework'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($configured_events as $event): ?>
                                <tr data-event-id="<?php echo esc_attr($event->id); ?>">
                                    <td><?php echo esc_html($event->event_name); ?></td>
                                    <td title="<?php echo esc_attr($event->api_endpoint); ?>">
                                        <?php echo esc_html(substr($event->api_endpoint, 0, 40)) . (strlen($event->api_endpoint) > 40 ? '...' : ''); ?>
                                    </td>
                                    <td><?php echo esc_html($event->http_method); ?></td>
                                    <td>
                                        <span class="aef-status aef-status-<?php echo $event->is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo $event->is_active ? __('Active', 'api-event-framework') : __('Inactive', 'api-event-framework'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="button button-small edit-event" data-event-id="<?php echo esc_attr($event->id); ?>">
                                            <?php _e('Edit', 'api-event-framework'); ?>
                                        </button>
                                        <button class="button button-small toggle-event" data-event-id="<?php echo esc_attr($event->id); ?>">
                                            <?php echo $event->is_active ? __('Disable', 'api-event-framework') : __('Enable', 'api-event-framework'); ?>
                                        </button>
                                        <button class="button button-small button-link-delete delete-event" data-event-id="<?php echo esc_attr($event->id); ?>">
                                            <?php _e('Delete', 'api-event-framework'); ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function logs_page()
    {
        $this->logger = new AEF_Logger();
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 25;
        $offset = ($page - 1) * $per_page;
        
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
        $event_filter = isset($_GET['event']) ? sanitize_text_field($_GET['event']) : null;
        
        $logs = $this->logger->get_logs($per_page, $offset, $status_filter, $event_filter);
        
        ?>
        <div class="wrap">
            <h1><?php _e('API Call Logs', 'api-event-framework'); ?></h1>
            
            <div class="aef-logs-filters">
                <form method="get">
                    <input type="hidden" name="page" value="aef-logs">
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'api-event-framework'); ?></option>
                        <option value="success" <?php selected($status_filter, 'success'); ?>><?php _e('Success', 'api-event-framework'); ?></option>
                        <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('Failed', 'api-event-framework'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'api-event-framework'); ?></option>
                    </select>
                    <input type="text" name="event" value="<?php echo esc_attr($event_filter); ?>" placeholder="<?php _e('Filter by event name', 'api-event-framework'); ?>">
                    <button type="submit" class="button"><?php _e('Filter', 'api-event-framework'); ?></button>
                </form>
            </div>

            <?php if (empty($logs)): ?>
                <p><?php _e('No logs found.', 'api-event-framework'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Event', 'api-event-framework'); ?></th>
                            <th><?php _e('Endpoint', 'api-event-framework'); ?></th>
                            <th><?php _e('Status', 'api-event-framework'); ?></th>
                            <th><?php _e('Response Code', 'api-event-framework'); ?></th>
                            <th><?php _e('Attempts', 'api-event-framework'); ?></th>
                            <th><?php _e('Date', 'api-event-framework'); ?></th>
                            <th><?php _e('Actions', 'api-event-framework'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log->event_name); ?></td>
                            <td title="<?php echo esc_attr($log->api_endpoint); ?>">
                                <?php echo esc_html(substr($log->api_endpoint, 0, 30)) . (strlen($log->api_endpoint) > 30 ? '...' : ''); ?>
                            </td>
                            <td><span class="aef-status aef-status-<?php echo esc_attr($log->status); ?>"><?php echo esc_html(ucfirst($log->status)); ?></span></td>
                            <td><?php echo $log->response_code ? esc_html($log->response_code) : '-'; ?></td>
                            <td><?php echo esc_html($log->attempt_count); ?></td>
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td>
                                <button class="button button-small view-log-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                    <?php _e('Details', 'api-event-framework'); ?>
                                </button>
                                <?php if ($log->status === 'failed'): ?>
                                <button class="button button-small retry-failed-call" data-log-id="<?php echo esc_attr($log->id); ?>">
                                    <?php _e('Retry', 'api-event-framework'); ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function settings_page()
    {
        $settings = get_option('aef_settings', []);
        
        ?>
        <div class="wrap">
            <h1><?php _e('API Event Framework Settings', 'api-event-framework'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('aef_settings_group'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="enabled"><?php _e('Enable Framework', 'api-event-framework'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="enabled" name="aef_settings[enabled]" value="1" 
                                       <?php checked(!empty($settings['enabled'])); ?>>
                                <?php _e('Enable API calls', 'api-event-framework'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="timeout"><?php _e('Request Timeout', 'api-event-framework'); ?></label></th>
                        <td>
                            <input type="number" id="timeout" name="aef_settings[timeout]" min="5" max="300" 
                                   value="<?php echo esc_attr($settings['timeout'] ?? 30); ?>">
                            <p class="description"><?php _e('Timeout in seconds for API requests', 'api-event-framework'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="retry_delay"><?php _e('Retry Delay', 'api-event-framework'); ?></label></th>
                        <td>
                            <input type="number" id="retry_delay" name="aef_settings[retry_delay]" min="60" max="3600" 
                                   value="<?php echo esc_attr($settings['retry_delay'] ?? 300); ?>">
                            <p class="description"><?php _e('Delay in seconds before retrying failed calls', 'api-event-framework'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="max_retry_attempts"><?php _e('Maximum Retry Attempts', 'api-event-framework'); ?></label></th>
                        <td>
                            <input type="number" id="max_retry_attempts" name="aef_settings[max_retry_attempts]" min="0" max="10" 
                                   value="<?php echo esc_attr($settings['max_retry_attempts'] ?? 3); ?>">
                            <p class="description"><?php _e('Maximum number of retry attempts for failed calls', 'api-event-framework'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="log_retention_days"><?php _e('Log Retention', 'api-event-framework'); ?></label></th>
                        <td>
                            <input type="number" id="log_retention_days" name="aef_settings[log_retention_days]" min="1" max="365" 
                                   value="<?php echo esc_attr($settings['log_retention_days'] ?? 30); ?>">
                            <p class="description"><?php _e('Number of days to keep logs before automatic cleanup', 'api-event-framework'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function ajax_save_event()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'api-event-framework'));
        }

        check_ajax_referer('aef_admin_nonce', 'nonce');

        $data = AEF_Security::sanitize_event_config($_POST);

        $endpoint_validation = AEF_Security::validate_api_endpoint($data['api_endpoint']);
        if (is_wp_error($endpoint_validation)) {
            wp_send_json_error($endpoint_validation->get_error_message());
        }

        $header_validation = AEF_Security::validate_headers($data['headers']);
        if (is_wp_error($header_validation)) {
            wp_send_json_error($header_validation->get_error_message());
        }

        $template_validation = AEF_Security::validate_payload_template($data['payload_template']);
        if (is_wp_error($template_validation)) {
            wp_send_json_error($template_validation->get_error_message());
        }

        $this->event_registry = new AEF_Event_Registry(new AEF_Logger());
        
        $result = $this->event_registry->save_event_configuration(
            $data['event_name'],
            $data['api_endpoint'],
            $data['http_method'],
            json_decode($data['headers'], true),
            $data['payload_template'],
            $data['is_active'],
            $data['retry_attempts']
        );

        if ($result) {
            wp_send_json_success(__('Event configuration saved successfully', 'api-event-framework'));
        } else {
            wp_send_json_error(__('Failed to save event configuration', 'api-event-framework'));
        }
    }

    public function ajax_delete_event()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'api-event-framework'));
        }

        check_ajax_referer('aef_admin_nonce', 'nonce');

        $event_id = intval($_POST['event_id']);
        
        global $wpdb;
        $events_table = $wpdb->prefix . 'aef_events';
        
        $result = $wpdb->delete($events_table, ['id' => $event_id], ['%d']);

        if ($result !== false) {
            wp_send_json_success(__('Event configuration deleted', 'api-event-framework'));
        } else {
            wp_send_json_error(__('Failed to delete event configuration', 'api-event-framework'));
        }
    }

    public function ajax_toggle_event()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'api-event-framework'));
        }

        check_ajax_referer('aef_admin_nonce', 'nonce');

        $event_id = intval($_POST['event_id']);
        
        global $wpdb;
        $events_table = $wpdb->prefix . 'aef_events';
        
        $current_status = $wpdb->get_var(
            $wpdb->prepare("SELECT is_active FROM {$events_table} WHERE id = %d", $event_id)
        );

        $new_status = $current_status ? 0 : 1;
        
        $result = $wpdb->update(
            $events_table,
            ['is_active' => $new_status],
            ['id' => $event_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $status_text = $new_status ? __('Active', 'api-event-framework') : __('Inactive', 'api-event-framework');
            wp_send_json_success([
                'status' => $new_status,
                'status_text' => $status_text
            ]);
        } else {
            wp_send_json_error(__('Failed to update event status', 'api-event-framework'));
        }
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];
        
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['timeout'] = max(5, min(300, intval($input['timeout'] ?? 30)));
        $sanitized['retry_delay'] = max(60, min(3600, intval($input['retry_delay'] ?? 300)));
        $sanitized['max_retry_attempts'] = max(0, min(10, intval($input['max_retry_attempts'] ?? 3)));
        $sanitized['log_retention_days'] = max(1, min(365, intval($input['log_retention_days'] ?? 30)));

        return $sanitized;
    }
}