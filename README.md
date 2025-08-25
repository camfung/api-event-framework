# php version 
8.2.29

# API Event Framework for WordPress

A powerful, decoupled WordPress plugin framework that triggers API calls on user events. Built with security, performance, and extensibility in mind.

## Features

- **Event-Driven Architecture**: Automatically trigger API calls on WordPress events (user registration, login, post publish, etc.)
- **Configurable API Endpoints**: Easy-to-use admin interface for managing API configurations
- **Template System**: Flexible payload templates with variable substitution
- **Retry Mechanism**: Automatic retry for failed API calls with configurable attempts
- **Security First**: Built-in validation, sanitization, and security measures
- **Comprehensive Logging**: Track all API calls with detailed logs and statistics
- **Admin Dashboard**: User-friendly interface for monitoring and configuration
- **Extensible**: Hooks and filters for custom functionality

## Installation

1. Upload the plugin files to your `/wp-content/plugins/api-event-framework/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'API Events' in your WordPress admin to configure

## Supported Events

### Built-in Events
- **User Registration** (`user_register`) - When new users sign up
- **User Login** (`wp_login`) - When users log in
- **User Logout** (`wp_logout`) - When users log out
- **Profile Update** (`profile_update`) - When users update their profiles
- **Post Published** (`publish_post`) - When posts are published
- **Comment Added** (`wp_insert_comment`) - When comments are approved

### Custom Events
You can register custom events using the provided hooks:

```php
// Register a custom event
add_action('aef_register_custom_events', function($registry) {
    $registry->register_custom_event('custom_purchase', [
        'name' => 'Purchase Complete',
        'description' => 'Triggered when a purchase is completed',
        'data_context' => ['order_id', 'user_id', 'amount', 'product_name']
    ]);
});

// Trigger the custom event
do_action('aef_custom_purchase', [
    'order_id' => 123,
    'user_id' => 456,
    'amount' => 99.99,
    'product_name' => 'Premium Plugin'
]);
```

## Configuration

### API Endpoint Setup

1. Go to **API Events > Events** in your WordPress admin
2. Select an event type from the dropdown
3. Enter your API endpoint URL
4. Choose HTTP method (GET, POST, PUT, PATCH, DELETE)
5. Add custom headers if needed (JSON format)
6. Configure payload template with variables
7. Set retry attempts and activation status

### Payload Templates

Use template variables in your payload to include event data:

```json
{
    "event": "user_registration",
    "user_id": "{{user_id}}",
    "email": "{{user_email}}",
    "username": "{{user_login}}",
    "timestamp": "{{timestamp}}",
    "site_url": "{{site_url}}",
    "site_name": "{{site_name}}"
}
```

### Available Variables

Variables depend on the event type:

- **User Events**: `user_id`, `user_email`, `user_login`, `display_name`, etc.
- **Post Events**: `post_id`, `post_title`, `post_author`, `post_content`, etc.
- **Comment Events**: `comment_id`, `comment_author`, `comment_content`, etc.
- **System Variables**: `timestamp`, `site_url`, `site_name`

## Security Features

- **URL Validation**: Only allows HTTP/HTTPS protocols
- **Host Blocking**: Configurable blocked hosts (localhost blocked by default)
- **Port Restrictions**: Blocks dangerous ports (SSH, database ports, etc.)
- **Header Sanitization**: Validates and sanitizes custom headers
- **Template Validation**: Prevents dangerous template variables
- **Rate Limiting**: Configurable rate limits per user
- **Data Masking**: Sensitive data is masked in logs
- **Nonce Protection**: All admin actions are nonce-protected

## Hooks & Filters

### Actions

```php
// Fired when an event is triggered
add_action('aef_event_triggered', function($event_name, $event_data) {
    // Custom logic when events are triggered
});

// Fired on successful API calls
add_action('aef_api_call_success', function($event_config, $event_data, $response) {
    // Handle successful API calls
});

// Fired on failed API calls
add_action('aef_api_call_failed', function($event_config, $event_data, $response) {
    // Handle failed API calls
});

// Security event logging
add_action('aef_security_event', function($event_type, $details) {
    // Handle security events
});
```

### Filters

```php
// Modify API request arguments
add_filter('aef_api_request_args', function($args, $event_config, $event_data) {
    // Modify request arguments before sending
    return $args;
});

// Configure blocked hosts
add_filter('aef_blocked_hosts', function($hosts) {
    $hosts[] = 'example.com';
    return $hosts;
});

// Configure blocked ports
add_filter('aef_blocked_ports', function($ports) {
    $ports[] = 8080;
    return $ports;
});

// Set rate limits
add_filter('aef_rate_limit_per_hour', function($limit, $user_id) {
    return 200; // Allow 200 calls per hour
});

// Allow sensitive headers (use with caution)
add_filter('aef_allow_sensitive_header', function($allowed, $header_name, $header_value) {
    if ($header_name === 'authorization') {
        return true; // Allow authorization header
    }
    return $allowed;
});
```

## Admin Interface

### Dashboard
- View statistics (total calls, success rate, failures)
- Monitor recent activity
- Quick overview of system health

### Event Configuration
- Add/edit event configurations
- Test API calls before saving
- Enable/disable events
- View available template variables

### Logs
- Detailed API call logs
- Filter by status, event type, date
- Retry failed calls
- View request/response details

### Settings
- Enable/disable the framework
- Configure timeouts and retry settings
- Set log retention period
- Adjust system parameters

## Database Tables

The plugin creates two custom tables:

### `wp_aef_events`
Stores event configurations:
- Event name and API endpoint
- HTTP method and headers
- Payload template
- Retry settings and status

### `wp_aef_logs`
Stores API call logs:
- Request and response data
- Status and error messages
- Attempt count and timestamps

## Performance Considerations

- **Asynchronous Processing**: API calls are processed asynchronously to avoid blocking page loads
- **Efficient Logging**: Optimized database queries and indexing
- **Automatic Cleanup**: Old logs are automatically cleaned based on retention settings
- **Rate Limiting**: Prevents abuse and excessive API calls
- **Caching**: Uses WordPress transients for rate limiting and temporary data

## Troubleshooting

### Common Issues

1. **API calls not triggering**
   - Check if the framework is enabled in Settings
   - Verify the event configuration is active
   - Check WordPress cron is working

2. **Failed API calls**
   - Verify the endpoint URL is correct and accessible
   - Check authentication headers
   - Review logs for error details

3. **Template variables not working**
   - Ensure variable names match available context
   - Check JSON syntax in payload template
   - Verify event data is being captured

### Debug Mode

Enable WordPress debug mode to see detailed logging:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## License

GPL v2 or later

## Support

For support and feature requests, please create an issue in the plugin repository.