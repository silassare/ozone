# Examples

This section contains practical examples of using the OZone Framework.

## Basic API Example

Here's a simple example of creating an API endpoint with documentation:

```php
<?php

use OZONE\Core\App\Service;
use OZONE\Core\REST\Interfaces\ApiDocProviderInterface;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

class UserController extends Service implements ApiDocProviderInterface
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/users', [self::class, 'listUsers'])
               ->name('users.list');
        
        $router->get('/users/{id}', [self::class, 'getUser'])
               ->name('users.get');
        
        $router->post('/users', [self::class, 'createUser'])
               ->name('users.create');
    }
    
    public function listUsers(RouteInfo $route): array
    {
        // Your logic here
        return [
            'users' => [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com']
            ]
        ];
    }
    
    public function getUser(RouteInfo $route): array
    {
        $id = $route->getParam('id');
        // Your logic here
        return [
            'user' => ['id' => $id, 'name' => 'John Doe', 'email' => 'john@example.com']
        ];
    }
    
    public function createUser(RouteInfo $route): array
    {
        $data = $route->getRequest()->getParsedBody();
        // Your logic here
        return [
            'user' => ['id' => 3, 'name' => $data['name'], 'email' => $data['email']]
        ];
    }
    
    public static function apiDoc(ApiDoc $doc): void
    {
        $tag = $doc->addTag('Users', 'User management operations');
        
        // List users endpoint
        $doc->addOperationFromRoute(
            'users.list',
            'GET',
            'List Users',
            [
                $doc->success([
                    'users' => $doc->array($doc->object([
                        'id' => $doc->integer(['description' => 'User ID']),
                        'name' => $doc->string(['description' => 'User name']),
                        'email' => $doc->string(['description' => 'User email'])
                    ]))
                ])
            ],
            [
                'tags' => [$tag->name],
                'operationId' => 'User.list',
                'description' => 'Retrieve a list of all users',
            ]
        );
        
        // Get user endpoint
        $doc->addOperationFromRoute(
            'users.get',
            'GET',
            'Get User',
            [
                $doc->success([
                    'user' => $doc->object([
                        'id' => $doc->integer(['description' => 'User ID']),
                        'name' => $doc->string(['description' => 'User name']),
                        'email' => $doc->string(['description' => 'User email'])
                    ])
                ])
            ],
            [
                'tags' => [$tag->name],
                'operationId' => 'User.get',
                'description' => 'Get a specific user by ID',
                'parameters' => [
                    $doc->parameter('id', 'path', $doc->integer(), 'User ID')
                ]
            ]
        );
        
        // Create user endpoint
        $doc->addOperationFromRoute(
            'users.create',
            'POST',
            'Create User',
            [
                $doc->success([
                    'user' => $doc->object([
                        'id' => $doc->integer(['description' => 'User ID']),
                        'name' => $doc->string(['description' => 'User name']),
                        'email' => $doc->string(['description' => 'User email'])
                    ])
                ])
            ],
            [
                'tags' => [$tag->name],
                'operationId' => 'User.create',
                'description' => 'Create a new user',
                'requestBody' => $doc->requestBody([
                    'name' => $doc->string(['description' => 'User name']),
                    'email' => $doc->string(['description' => 'User email'])
                ])
            ]
        );
    }
}
```

## Web View Example

Here's an example of creating a web view with templates:

```php
<?php

use OZONE\Core\Web\WebView;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteInfo;

class HomeView extends WebView
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/', [self::class, 'home'])
               ->name('home');
    }
    
    public function home(RouteInfo $route)
    {
        $users = [
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com']
        ];
        
        return $this->setTemplate('home.blate')
                   ->inject([
                       'title' => 'Welcome to OZone',
                       'users' => $users
                   ])
                   ->respond();
    }
}
```

And the corresponding template (`home.blate`):

```html
<!DOCTYPE html>
<html>
<head>
    <title>{title}</title>
</head>
<body>
    <h1>{title}</h1>
    
    <h2>Users</h2>
    <ul>
        {@each users as user}
        <li>{user.name} - {user.email}</li>
        {/each}
    </ul>
</body>
</html>
```

## Custom Template Example

Example of registering custom template sources:

```php
<?php

use OZONE\Core\FS\Templates;

// Add custom template directory
Templates::addSource('/path/to/custom/templates');

// Compile template with data
$output = Templates::compile('custom-template.blate', [
    'title' => 'Custom Page',
    'content' => 'This is custom content'
]);
```

## Configuration Example

Example configuration file:

```php
<?php
// config/api.php

return [
    'enabled' => env('API_ENABLED', true),
    'documentation' => [
        'enabled' => env('API_DOC_ENABLED', true),
        'title' => env('API_DOC_TITLE', 'My API Documentation'),
        'version' => env('API_VERSION', '1.0.0'),
    ],
    'rate_limiting' => [
        'enabled' => env('RATE_LIMIT_ENABLED', false),
        'max_requests' => env('RATE_LIMIT_MAX', 60),
        'window_minutes' => env('RATE_LIMIT_WINDOW', 1),
    ]
];
```

## Event Handling Example

Example of handling the ApiDocReady event:

```php
<?php

use OZONE\Core\REST\Events\ApiDocReady;
use PHPUtils\Events\EventDispatcher;

$dispatcher = new EventDispatcher();

$dispatcher->listen(ApiDocReady::class, function(ApiDocReady $event) {
    $doc = $event->doc;
    
    // Add global information
    $doc->getOpenApi()->info->title = 'My Custom API';
    $doc->getOpenApi()->info->version = '2.0.0';
    
    // Add custom tags
    $doc->addTag('Custom', 'Custom operations added via event');
});
```

## More Examples

For more examples and use cases, check out:

- [Guide Section](/guide/) - Step-by-step tutorials
- [API Reference](/api/) - Detailed API documentation
- [GitHub Repository](https://github.com/silassare/ozone) - Source code and examples