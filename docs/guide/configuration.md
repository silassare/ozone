# Configuration

Learn how to configure your OZone application for different environments and use cases.

## Configuration Files

OZone uses a flexible configuration system located in the `oz/oz_settings/` directory:

- `oz.api.doc.php` - API documentation settings
- `oz.routes.php` - Route configuration
- Other configuration files for various components

## API Documentation Settings

Configure API documentation in `oz/oz_settings/oz.api.doc.php`:

```php
<?php
return [
    // Enable API documentation page
    'OZ_API_DOC_ENABLED' => true,
    'OZ_API_DOC_SHOW_ON_INDEX' => true,
];
```

### Available Options

| Setting | Description | Default |
|---------|-------------|---------|
| `OZ_API_DOC_ENABLED` | Enable/disable API documentation | `true` |
| `OZ_API_DOC_SHOW_ON_INDEX` | Show documentation link on index | `true` |

## Route Configuration

Routes are configured in `oz/oz_settings/oz.routes.php`:

```php
<?php
use OZONE\Core\REST\Views\ApiDocView;
use OZONE\Core\Web\Views\RedirectView;

return [
    RedirectView::class => true,
    ApiDocView::class => true,
    // Add your custom routes here
];
```

## Environment Variables

OZone supports environment-based configuration using environment variables. You can set these in your system or use a `.env` file:

```bash
OZ_API_DOC_ENABLED=true
OZ_DEBUG=false
OZ_ENVIRONMENT=production
```

## Database Configuration

Configure your database connection:

```php
<?php
return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'my_database',
    'username' => 'db_user',
    'password' => 'db_password',
    'charset' => 'utf8mb4',
];
```

## Template Configuration

Configure template settings:

```php
<?php
return [
    'template_dir' => 'templates/',
    'cache_enabled' => true,
    'auto_reload' => false,
];
```

## Security Configuration

Configure security settings:

```php
<?php
return [
    'csrf_protection' => true,
    'session_secure' => true,
    'password_hash_algo' => PASSWORD_ARGON2ID,
];
```

## Caching

Configure caching settings:

```php
<?php
return [
    'cache_driver' => 'file',
    'cache_ttl' => 3600,
    'cache_prefix' => 'oz_',
];
```

## Development vs Production

### Development Configuration

For development environments:

```php
<?php
return [
    'debug' => true,
    'error_reporting' => E_ALL,
    'display_errors' => true,
    'template_cache' => false,
];
```

### Production Configuration

For production environments:

```php
<?php
return [
    'debug' => false,
    'error_reporting' => E_ERROR,
    'display_errors' => false,
    'template_cache' => true,
];
```

## Configuration Best Practices

1. **Use Environment Variables**: Store sensitive data in environment variables
2. **Separate Configurations**: Use different configurations for different environments
3. **Version Control**: Don't commit sensitive configuration files
4. **Validate Settings**: Always validate configuration values
5. **Documentation**: Document custom configuration options

## Next Steps

With your configuration set up, you can now:

- Learn about [Routing](/guide/routing) to create endpoints
- Explore [Views](/guide/views) for rendering templates
- Set up [API Documentation](/guide/api-docs) for your APIs