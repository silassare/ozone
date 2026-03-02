# REST API Classes

Documentation for REST API specific classes and components.

## ApiDoc Class

Main class for generating OpenAPI documentation.

### Properties

- `protected OpenApi $openapi` - The OpenAPI specification object
- `protected OA\Info $api_info` - API information
- `private static ?self $instance = null` - Singleton instance

### Methods

#### `get(Context $context): static`

Gets the singleton ApiDoc instance.

```php
use OZONE\Core\REST\ApiDoc;

$doc = ApiDoc::get($context);
```

#### `addTag(string $name, string $description): OA\Tag`

Adds a tag to the API documentation.

```php
$tag = $doc->addTag('Users', 'User management operations');
```

#### `addOperationFromRoute(string $routeName, string $method, string $summary, array $responses, array $options = []): void`

Adds an operation from a named route.

```php
$doc->addOperationFromRoute(
    'users.list',
    'GET',
    'List Users',
    [$doc->success(['users' => $doc->array($userSchema)])],
    ['tags' => ['Users']]
);
```

#### `viewInject(): array`

Returns data for injecting into documentation views.

```php
$data = $doc->viewInject();
// Returns: ['api_doc_title', 'api_doc_spec_url', 'api_doc_spec']
```

## ApiDocView Class

View class for rendering API documentation.

### Constants

- `API_DOC_VIEW_ROUTE = 'oz:api-doc-view'` - Route name for the documentation view

### Methods

#### `registerRoutes(Router $router): void`

Registers the API documentation routes.

```php
ApiDocView::registerRoutes($router);
```

## ApiDocService Class

Service for serving API documentation specifications.

### Constants

- `API_DOC_SPEC_ROUTE = 'oz:api-doc-spec'` - Route name for the API specification

### Methods

#### `registerRoutes(Router $router): void`

Registers the API specification route.

#### `apiDoc(ApiDoc $doc): void`

Adds API documentation for the service itself.

## ApiDocProviderInterface

Interface for classes that provide API documentation.

### Methods

#### `apiDoc(ApiDoc $doc): void`

Method called to add API documentation.

```php
<?php

use OZONE\Core\REST\Interfaces\ApiDocProviderInterface;
use OZONE\Core\REST\ApiDoc;

class MyService implements ApiDocProviderInterface
{
    public static function apiDoc(ApiDoc $doc): void
    {
        $tag = $doc->addTag('MyService', 'My service operations');
        
        $doc->addOperationFromRoute(
            'my.route',
            'GET',
            'Get Data',
            [$doc->success(['data' => 'array'])],
            ['tags' => [$tag->name]]
        );
    }
}
```

## ApiDocReady Event

Event dispatched when API documentation is ready.

### Properties

- `public readonly ApiDoc $doc` - The API documentation instance

### Usage

```php
use OZONE\Core\REST\Events\ApiDocReady;

$dispatcher->listen(ApiDocReady::class, function(ApiDocReady $event) {
    $doc = $event->doc;
    
    // Modify documentation
    $doc->addTag('Custom', 'Custom operations');
});
```

## Schema Builders

The ApiDoc class provides methods for building OpenAPI schemas:

### Basic Types

```php
$doc->string(['description' => 'A string value'])
$doc->integer(['description' => 'An integer value'])
$doc->number(['description' => 'A numeric value'])
$doc->boolean(['description' => 'A boolean value'])
$doc->array($itemSchema, ['description' => 'An array'])
$doc->object($properties, ['description' => 'An object'])
```

### Response Builders

```php
// Success response
$doc->success([
    'data' => $doc->array($itemSchema),
    'meta' => $doc->object([
        'total' => $doc->integer(),
        'page' => $doc->integer()
    ])
])

// Error response
$doc->error([
    'message' => $doc->string(['description' => 'Error message']),
    'code' => $doc->string(['description' => 'Error code'])
])
```

### Request Bodies

```php
$doc->requestBody([
    'name' => $doc->string(['description' => 'User name']),
    'email' => $doc->string(['format' => 'email']),
    'age' => $doc->integer(['minimum' => 0])
])
```

### Parameters

```php
$doc->parameter('id', 'path', $doc->integer(), 'User ID')
$doc->parameter('limit', 'query', $doc->integer(), 'Results limit')
```

## Configuration

API documentation is configured through settings:

```php
// oz/oz_settings/oz.api.doc.php
return [
    'OZ_API_DOC_ENABLED' => true,
    'OZ_API_DOC_SHOW_ON_INDEX' => true,
];
```

## Templates

The API documentation uses the `oz.api.doc.view.blate` template for rendering:

```html
<!DOCTYPE html>
<html>
<head>
    <title>{api_doc_title}</title>
    <script>
        window.api_doc_spec_url = "{api_doc_spec_url}";
    </script>
</head>
<body>
    <div id="api-doc-wrapper"></div>
    <script src="https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js"></script>
    <script>
        fetch(window.api_doc_spec_url)
            .then(response => response.json())
            .then(data => {
                Redoc.init(data.data.spec, {}, document.getElementById('api-doc-wrapper'));
            });
    </script>
</body>
</html>
```

## Next Steps

- Check out the [Core Classes](/api/core) documentation
- See [Examples](/examples/) for practical usage
- Read the [API Documentation Guide](/guide/api-docs) for detailed tutorials