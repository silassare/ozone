# API Documentation

OZone provides powerful built-in API documentation features using OpenAPI specifications.

## Overview

OZone automatically generates comprehensive API documentation for your endpoints using:

- **OpenAPI 3.0** specifications
- **ReDoc** for beautiful documentation rendering
- **Automatic route discovery** from your application
- **Interactive API explorer**

## Enabling API Documentation

API documentation is controlled by configuration settings in `oz/oz_settings/oz.api.doc.php`:

```php
<?php
return [
    'OZ_API_DOC_ENABLED' => true,
    'OZ_API_DOC_SHOW_ON_INDEX' => true,
];
```

## Accessing Documentation

When enabled, your API documentation is available at:

- **Documentation View**: `/api-doc-view.html`
- **OpenAPI Specification**: `/api-doc-spec.json`

## ApiDoc Class

The core `ApiDoc` class handles documentation generation:

```php
use OZONE\Core\REST\ApiDoc;

// Get the API documentation instance
$doc = ApiDoc::get($context);

// Add custom documentation
$tag = $doc->addTag('Users', 'User management operations');
```

## Creating API Documentation

### Using ApiDocProviderInterface

Implement the `ApiDocProviderInterface` in your classes:

```php
<?php
use OZONE\Core\REST\Interfaces\ApiDocProviderInterface;
use OZONE\Core\REST\ApiDoc;

class UserService implements ApiDocProviderInterface
{
    public static function apiDoc(ApiDoc $doc): void
    {
        $tag = $doc->addTag('Users', 'User management operations');
        
        $doc->addOperationFromRoute(
            'user.list',
            'GET',
            'List Users',
            [
                $doc->success([
                    'users' => $doc->array(
                        $doc->object(['id' => 'integer', 'name' => 'string'])
                    )
                ])
            ],
            [
                'tags' => [$tag->name],
                'operationId' => 'User.list',
                'description' => 'Retrieve a list of all users',
            ]
        );
    }
}
```

### Manual Documentation

You can also add documentation manually:

```php
$doc = ApiDoc::get($context);

// Add a tag
$tag = $doc->addTag('Custom', 'Custom operations');

// Define schemas
$userSchema = $doc->object([
    'id' => $doc->integer(['description' => 'User ID']),
    'name' => $doc->string(['description' => 'User name']),
    'email' => $doc->string(['format' => 'email'])
]);

// Add operation
$doc->addOperation(
    '/users',
    'GET',
    'List Users',
    [
        $doc->success(['users' => $doc->array($userSchema)])
    ],
    [
        'tags' => [$tag->name],
        'operationId' => 'listUsers'
    ]
);
```

## Documentation Templates

The documentation view uses the `oz.api.doc.view.blate` template:

```html
<!DOCTYPE html>
<html lang="{oz.getLanguage()}">
<head>
    <title>{api_doc_title}</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

## Events

The `ApiDocReady` event is dispatched when documentation is ready:

```php
use OZONE\Core\REST\Events\ApiDocReady;

// Listen for the event
$dispatcher->listen(ApiDocReady::class, function(ApiDocReady $event) {
    $doc = $event->doc;
    // Modify documentation before it's served
});
```

## Customization

### Custom Templates

You can customize the documentation template by:

1. Creating a custom template file
2. Registering it in your template sources
3. Updating the view to use your template

### Custom Styling

Add custom CSS to style your documentation:

```html
<style>
    .redoc-wrap {
        background-color: #f5f5f5;
    }
    
    .redoc-wrap .api-info-wrap h1 {
        color: #2c3e50;
    }
</style>
```

## Best Practices

1. **Consistent Documentation**: Use consistent naming and descriptions
2. **Examples**: Provide examples for complex operations
3. **Error Responses**: Document all possible error responses
4. **Authentication**: Clearly document authentication requirements
5. **Versioning**: Document API versioning strategy

## Advanced Features

### Response Schemas

Define detailed response schemas:

```php
$doc->success([
    'data' => $doc->object([
        'id' => $doc->integer(),
        'attributes' => $doc->object([
            'name' => $doc->string(),
            'created_at' => $doc->string(['format' => 'date-time'])
        ])
    ]),
    'meta' => $doc->object([
        'total' => $doc->integer(),
        'per_page' => $doc->integer()
    ])
]);
```

### Security Schemes

Add authentication documentation:

```php
$doc->addSecurityScheme('bearerAuth', [
    'type' => 'http',
    'scheme' => 'bearer',
    'bearerFormat' => 'JWT'
]);
```

## Troubleshooting

### Documentation Not Showing

1. Check that `OZ_API_DOC_ENABLED` is set to `true`
2. Verify your routes are properly registered
3. Ensure the ApiDocView is enabled in your routes configuration

### Missing Operations

1. Implement `ApiDocProviderInterface` in your service classes
2. Register your providers in the routes configuration
3. Use `addOperationFromRoute()` method correctly

## Next Steps

- Learn about [Routing](/guide/routing) to create documented endpoints
- Explore [Views](/guide/views) for custom documentation templates
- Check out [Controllers](/guide/controllers) for API development