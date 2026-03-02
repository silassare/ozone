# Core Classes

This section documents the core classes and components of the OZone Framework.

## Templates Class

The `Templates` class manages template compilation and source resolution.

### Methods

#### `getSources(): PathSources`

Gets the template path sources configuration.

```php
use OZONE\Core\FS\Templates;

$sources = Templates::getSources();
```

#### `addSource(string $path): void`

Adds a template source directory.

```php
Templates::addSource('/path/to/custom/templates');
```

#### `compile(string $template, array $data): string`

Compiles a template with the given data.

```php
$output = Templates::compile('template.blate', [
    'title' => 'Page Title',
    'content' => 'Page content'
]);
```

#### `localize(string $template): false|string`

Localizes a template path to an absolute file system path.

```php
$path = Templates::localize('oz://template.blate');
```

## WebView Class

Base class for web views and template rendering.

### Properties

- `protected string $template` - The template to render
- `protected array $compile_data` - Data to inject into the template

### Methods

#### `setTemplate(string $template): static`

Sets the template to render.

```php
$view->setTemplate('my-template.blate');
```

#### `inject(array $data): static`

Injects data into the template.

```php
$view->inject(['key' => 'value']);
```

#### `injectKey(string $key, mixed $value): static`

Injects a single key-value pair.

```php
$view->injectKey('title', 'Page Title');
```

#### `respond(): Response`

Renders the view and returns an HTTP response.

```php
return $view->respond();
```

## Service Class

Base class for application services and controllers.

### Methods

#### `json(): self`

Sets the response to JSON mode.

```php
$this->json()->setData(['key' => 'value']);
```

#### `respond(): Response`

Returns the configured response.

```php
return $this->respond();
```

## Context Class

Application context providing access to request, response, and configuration.

### Methods

#### `getRequest(): Request`

Gets the current HTTP request.

#### `getResponse(): Response`

Gets the current HTTP response.

#### `buildUri(string $path, array $params = []): string`

Builds a complete URI.

#### `buildRouteUri(string $name, array $params = []): string`

Builds a URI for a named route.

## Settings Class

Configuration management class.

### Methods

#### `get(string $key, mixed $default = null): mixed`

Gets a configuration value.

```php
use OZONE\Core\App\Settings;

$value = Settings::get('app.name', 'Default App Name');
```

## Next Steps

- Explore [REST API Classes](/api/rest) for API-specific components
- Check the [Examples](/examples/) for practical usage
- Review the [Guide](/guide/) for detailed tutorials