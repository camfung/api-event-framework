# API Event Framework - File Structure and Function Breakdown

## Core Plugin File

### `api-event-framework.php`
**Main plugin entry point and orchestrator**

**Functions:**
- `ApiEventFramework::get_instance()` - Singleton pattern implementation
- `ApiEventFramework::init()` - Initialize plugin hooks and actions
- `ApiEventFramework::load_textdomain()` - Load translation files
- `ApiEventFramework::initialize_components()` - Bootstrap all plugin components
- `ApiEventFramework::load_dependencies()` - Include all class files
- `ApiEventFramework::activate()` - Plugin activation handler
- `ApiEventFramework::deactivate()` - Plugin deactivation handler
- `ApiEventFramework::create_tables()` - Create database tables on activation
- `ApiEventFramework::set_default_options()` - Set default configuration options
- `aef_init()` - Global function to initialize plugin

**Database Tables Created:**
- `wp_aef_events` - Event configurations
- `wp_aef_logs` - API call logs

## Core Component Classes

### `includes/class-aef-logger.php`
**Handles all logging operations and database interactions**

**Functions:**
- `AEF_Logger::__construct()` - Initialize logger with database table reference
- `AEF_Logger::log_api_call()` - Create initial log entry for API call
- `AEF_Logger::update_log_entry()` - Update existing log with response data
- `AEF_Logger::get_logs()` - Retrieve logs with filtering and pagination
- `AEF_Logger::get_failed_logs()` - Get logs with failed status for retry
- `AEF_Logger::cleanup_old_logs()` - Remove logs older than retention period
- `AEF_Logger::get_stats()` - Generate dashboard statistics
- `AEF_Logger::debug_log()` - Write debug messages when WP_DEBUG enabled

**Database Operations:**
- Insert new log entries
- Update log status and response data
- Query logs with filters
- Generate statistics
- Clean up old records

### `includes/class-aef-event-registry.php`
**Manages WordPress event hooks and triggers API calls**

**Functions:**
- `AEF_Event_Registry::__construct()` - Initialize with logger dependency
- `AEF_Event_Registry::init()` - Register WordPress hooks and schedule cron
- `AEF_Event_Registry::register_wordpress_hooks()` - Hook into WordPress events
- `AEF_Event_Registry::handle_user_register()` - Process user registration events
- `AEF_Event_Registry::handle_user_login()` - Process user login events
- `AEF_Event_Registry::handle_user_logout()` - Process user logout events
- `AEF_Event_Registry::handle_profile_update()` - Process profile update events
- `AEF_Event_Registry::handle_post_publish()` - Process post publishing events
- `AEF_Event_Registry::handle_comment_insert()` - Process comment approval events
- `AEF_Event_Registry::trigger_event()` - Main event triggering logic
- `AEF_Event_Registry::register_custom_event()` - Register custom events
- `AEF_Event_Registry::get_available_events()` - Get all available event types
- `AEF_Event_Registry::get_configured_events()` - Get configured event settings
- `AEF_Event_Registry::save_event_configuration()` - Save/update event config
- `AEF_Event_Registry::retry_failed_api_calls()` - Scheduled retry of failed calls
- `AEF_Event_Registry::get_updated_fields()` - Compare old/new user data
- `AEF_Event_Registry::sanitize_user_data()` - Clean user data for logging

**WordPress Events Handled:**
- `user_register` - New user registration
- `wp_login` - User login
- `wp_logout` - User logout  
- `profile_update` - Profile changes
- `publish_post` - Post publishing
- `wp_insert_comment` - Comment approval

### `includes/class-aef-api-manager.php`
**Executes API calls and handles responses**

**Functions:**
- `AEF_API_Manager::__construct()` - Initialize with logger and settings
- `AEF_API_Manager::init()` - Register AJAX handlers
- `AEF_API_Manager::make_api_call()` - Execute API call with logging
- `AEF_API_Manager::schedule_async_api_call()` - Schedule non-blocking API call
- `AEF_API_Manager::process_api_call()` - Process scheduled API call
- `AEF_API_Manager::handle_api_error()` - Handle failed API calls and retries
- `AEF_API_Manager::retry_api_call()` - Retry failed API call
- `AEF_API_Manager::build_payload()` - Build API payload from template
- `AEF_API_Manager::build_headers()` - Build HTTP headers
- `AEF_API_Manager::replace_template_variables()` - Replace template placeholders
- `AEF_API_Manager::extract_template_variables()` - Extract variables from data
- `AEF_API_Manager::ajax_test_api_call()` - Test API endpoint via AJAX
- `AEF_API_Manager::ajax_retry_failed_call()` - Retry failed call via AJAX
- `AEF_API_Manager::is_enabled()` - Check if framework is enabled
- `AEF_API_Manager::get_timeout()` - Get request timeout setting
- `AEF_API_Manager::get_retry_delay()` - Get retry delay setting
- `AEF_API_Manager::get_max_retry_attempts()` - Get max retry attempts

**API Operations:**
- HTTP requests (GET, POST, PUT, PATCH, DELETE)
- Template variable substitution
- Asynchronous processing
- Retry mechanism with delays
- Response logging and error handling

### `includes/class-aef-security.php`
**Security validation and sanitization utilities**

**Functions:**
- `AEF_Security::sanitize_event_config()` - Sanitize event configuration data
- `AEF_Security::sanitize_json_field()` - Validate and sanitize JSON input
- `AEF_Security::validate_api_endpoint()` - Validate API endpoint URLs
- `AEF_Security::validate_payload_template()` - Validate payload templates
- `AEF_Security::validate_headers()` - Validate HTTP headers
- `AEF_Security::check_rate_limit()` - Enforce rate limiting
- `AEF_Security::mask_sensitive_data()` - Mask sensitive data in logs
- `AEF_Security::log_security_event()` - Log security-related events
- `AEF_Security::get_client_ip()` - Get real client IP address
- `AEF_Security::is_allowed_port()` - Check if port is allowed
- `AEF_Security::encrypt_sensitive_data()` - Encrypt sensitive data
- `AEF_Security::decrypt_sensitive_data()` - Decrypt sensitive data

**Security Features:**
- URL scheme validation (HTTP/HTTPS only)
- Blocked host prevention
- Port restrictions (blocks dangerous ports)
- Header validation and sanitization
- Rate limiting per user
- Sensitive data masking
- IP address tracking

### `includes/class-aef-admin-interface.php`
**WordPress admin interface and AJAX handlers**

**Functions:**
- `AEF_Admin_Interface::init()` - Initialize admin hooks and actions
- `AEF_Admin_Interface::add_admin_menu()` - Add admin menu pages
- `AEF_Admin_Interface::register_settings()` - Register WordPress settings
- `AEF_Admin_Interface::enqueue_admin_scripts()` - Load CSS/JS files
- `AEF_Admin_Interface::dashboard_page()` - Render dashboard page
- `AEF_Admin_Interface::events_page()` - Render events configuration page
- `AEF_Admin_Interface::logs_page()` - Render logs viewing page
- `AEF_Admin_Interface::settings_page()` - Render settings page
- `AEF_Admin_Interface::ajax_save_event()` - Save event configuration via AJAX
- `AEF_Admin_Interface::ajax_delete_event()` - Delete event via AJAX
- `AEF_Admin_Interface::ajax_toggle_event()` - Enable/disable event via AJAX
- `AEF_Admin_Interface::sanitize_settings()` - Sanitize settings input

**Admin Pages:**
- Dashboard - Statistics and recent activity
- Events - Configuration and management
- Logs - API call history and details
- Settings - Framework configuration

**AJAX Endpoints:**
- `aef_save_event` - Save event configuration
- `aef_delete_event` - Delete event configuration
- `aef_toggle_event` - Toggle event active status
- `aef_test_api_call` - Test API endpoint
- `aef_retry_failed_call` - Retry failed API call

## Frontend Assets

### `assets/admin.css`
**Admin interface styling**

**CSS Classes:**
- `.aef-dashboard` - Main dashboard layout
- `.aef-stats-grid` - Statistics grid layout
- `.aef-stat-card` - Individual stat cards with color coding
- `.aef-events-page` - Events configuration page layout
- `.aef-form` - Form styling and layout
- `.aef-status` - Status indicators with color coding
- `.aef-modal` - Modal dialog styling
- `.aef-logs-filters` - Log filtering interface

**Features:**
- Responsive grid layouts
- Color-coded status indicators
- Modal dialog styling
- Form and table styling
- Mobile-responsive design

### `assets/admin.js`
**Admin interface JavaScript functionality**

**Functions:**
- `saveEvent()` - Handle event configuration saving
- `testApiCall()` - Test API endpoints before saving
- `deleteEvent()` - Delete event configurations
- `toggleEvent()` - Enable/disable events
- `retryFailedCall()` - Retry failed API calls
- `updateEventDescription()` - Update event descriptions
- `updateAvailableVariables()` - Show available template variables
- `showTestResult()` - Display API test results
- `showMessage()` - Show admin notices
- `viewLogDetails()` - View detailed log information
- `showModal()` - Display modal dialogs
- `escapeHtml()` - HTML escaping utility

**AJAX Operations:**
- Form submission with validation
- API endpoint testing
- Event management (save/delete/toggle)
- Log operations and retries
- Real-time feedback and notifications

## Documentation

### `README.md`
**Comprehensive documentation including:**

**Sections:**
- Installation instructions
- Feature overview
- Configuration guide
- Security features
- Hooks and filters documentation
- Troubleshooting guide
- API reference
- Database schema
- Performance considerations
- Requirements and compatibility

**Code Examples:**
- Custom event registration
- Hook usage examples
- Filter implementations
- Template variable usage
- Security configuration

This file structure provides a complete, production-ready WordPress plugin framework with:
- **7 core PHP files** handling different aspects of functionality
- **2 frontend assets** for admin interface
- **1 comprehensive documentation** file
- **Modular architecture** with clear separation of concerns
- **Security-first approach** with extensive validation
- **User-friendly admin interface** with AJAX functionality
- **Extensible design** through WordPress hooks and filters