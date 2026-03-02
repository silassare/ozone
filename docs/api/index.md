# API Reference

This section provides comprehensive documentation for the OZone Framework API.

## Core Components

The OZone Framework consists of several key components:

### REST API Components

- **ApiDoc**: Main class for API documentation generation
- **ApiDocView**: View for rendering API documentation
- **ApiDocService**: Service for serving API specifications
- **ApiDocReady**: Event dispatched when documentation is ready

### Web Components

- **WebView**: Base class for web views and templates
- **Templates**: Template management and compilation
- **Router**: Request routing and middleware

### Core Services

- **Context**: Application context and dependency injection
- **Settings**: Configuration management
- **Cache**: Caching abstraction layer

## Quick Reference

### Creating API Documentation

```php
use OZONE\Core\REST\ApiDoc;

$doc = ApiDoc::get($context);
$tag = $doc->addTag('Users', 'User operations');
```

### Rendering Views

```php
use OZONE\Core\Web\WebView;

class MyView extends WebView 
{
    public function render(): Response 
    {
        return $this->setTemplate('my-template.blate')
                   ->inject(['data' => $data])
                   ->respond();
    }
}
```

### Template Compilation

```php
use OZONE\Core\FS\Templates;

$output = Templates::compile('template.blate', ['key' => 'value']);
```

## Detailed Documentation

For detailed information about specific components:

- [Core Classes](/api/core) - Core framework classes
- [REST API](/api/rest) - REST API components and services

## Examples

Common usage patterns and examples can be found in the [Examples](/examples/) section.

## Support

If you need help with the API:

1. Check the [Guide](/guide/) for tutorials
2. Review the examples in this reference
3. Open an issue on [GitHub](https://github.com/silassare/ozone/issues)