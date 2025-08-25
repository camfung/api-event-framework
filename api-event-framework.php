<?php
/**
 * Plugin Name: API Event Framework
 * Description: A framework for triggering API calls on WordPress user events
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: api-event-framework
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AEF_PLUGIN_FILE', __FILE__);
define('AEF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AEF_VERSION', '1.0.0');

class ApiEventFramework
{
    private static $instance = null;
    private $event_registry;
    private $api_manager;
    private $admin_interface;
    private $logger;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function init()
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'initialize_components']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('api-event-framework', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function initialize_components()
    {
        $this->load_dependencies();
        
        $this->logger = new AEF_Logger();
        $this->event_registry = new AEF_Event_Registry($this->logger);
        $this->api_manager = new AEF_API_Manager($this->logger);
        $this->admin_interface = new AEF_Admin_Interface();
        
        $this->event_registry->init();
        $this->api_manager->init();
        $this->admin_interface->init();
    }

    private function load_dependencies()
    {
        require_once AEF_PLUGIN_DIR . 'includes/class-aef-logger.php';
        require_once AEF_PLUGIN_DIR . 'includes/class-aef-event-registry.php';
        require_once AEF_PLUGIN_DIR . 'includes/class-aef-api-manager.php';
        require_once AEF_PLUGIN_DIR . 'includes/class-aef-admin-interface.php';
        require_once AEF_PLUGIN_DIR . 'includes/class-aef-security.php';
    }

    public function activate()
    {
        $this->create_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    private function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $events_table = $wpdb->prefix . 'aef_events';
        $logs_table = $wpdb->prefix . 'aef_logs';

        $events_sql = "CREATE TABLE $events_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_name varchar(100) NOT NULL,
            api_endpoint varchar(500) NOT NULL,
            http_method varchar(10) DEFAULT 'POST',
            headers longtext,
            payload_template longtext,
            is_active tinyint(1) DEFAULT 1,
            retry_attempts int(2) DEFAULT 3,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY event_name (event_name)
        ) $charset_collate;";

        $logs_sql = "CREATE TABLE $logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_id mediumint(9),
            event_name varchar(100) NOT NULL,
            api_endpoint varchar(500) NOT NULL,
            request_data longtext,
            response_code int(3),
            response_body longtext,
            status varchar(20) DEFAULT 'pending',
            error_message text,
            attempt_count int(2) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($events_sql);
        dbDelta($logs_sql);
    }

    private function set_default_options()
    {
        add_option('aef_settings', [
            'enabled' => true,
            'retry_delay' => 300,
            'max_retry_attempts' => 3,
            'timeout' => 30,
            'log_retention_days' => 30
        ]);
    }
}

function aef_init()
{
    return ApiEventFramework::get_instance();
}

add_action('plugins_loaded', 'aef_init');