# Routing

Learn how to define and manage routes in your OZone application.

## Overview

OZone provides a powerful routing system that supports both web and API routes. Routes are defined using the `Router` class and can be organized using service providers.

## Basic Routing

### Defining Routes

Routes are typically defined in classes that extend `Service` and implement the `registerRoutes` method:

```php
<?php

use OZONE\Core\App\Service;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteInfo;

class MyController extends Service
{
    public static function registerRoutes(Router $router): void
    {
        // GET route
        $router->get('/users', [self::class, 'listUsers'])
               ->name('users.list');
        
        // POST route
        $router->post('/users', [self::class, 'createUser'])
               ->name('users.create');
        
        // PUT route
        $router->put('/users/{id}', [self::class, 'updateUser'])
               ->name('users.update');
        
        // DELETE route
        $router->delete('/users/{id}', [self::class, 'deleteUser'])
               ->name('users.delete');
    }
    
    public function listUsers(RouteInfo $route)
    {
        return ['users' => []];
    }
    
    public function createUser(RouteInfo $route)
    {
        $data = $route->getRequest()->getParsedBody();
        return ['user' => $data];
    }
    
    public function updateUser(RouteInfo $route)
    {
        $id = $route->getParam('id');
        $data = $route->getRequest()->getParsedBody();
        return ['user' => array_merge(['id' => $id], $data)];
    }
    
    public function deleteUser(RouteInfo $route)
    {
        $id = $route->getParam('id');
        return ['success' => true, 'id' => $id];
    }
}
```

### Route Parameters

OZone supports dynamic route parameters:

```php
// Single parameter
$router->get('/users/{id}', [UserController::class, 'getUser']);

// Multiple parameters
$router->get('/users/{userId}/posts/{postId}', [PostController::class, 'getUserPost']);

// Optional parameters
$router->get('/posts/{id?}', [PostController::class, 'getPosts']);
```

Accessing parameters in your controller:

```php
public function getUser(RouteInfo $route)
{
    $id = $route->getParam('id');
    $userId = $route->getParam('userId');
    
    // Get all parameters
    $params = $route->getParams();
    
    return ['user_id' => $id];
}
```

## Route Groups

Group related routes together:

```php
$router->group('/api/v1', function(Router $router) {
    $router->get('/users', [UserController::class, 'list']);
    $router->get('/users/{id}', [UserController::class, 'get']);
    $router->post('/users', [UserController::class, 'create']);
});
```

## Middleware

Apply middleware to routes for authentication, logging, etc.:

```php
$router->get('/admin/dashboard', [AdminController::class, 'dashboard'])
       ->middleware(AuthMiddleware::class)
       ->middleware(AdminMiddleware::class);
```

## Named Routes

Give routes names for easy URL generation:

```php
$router->get('/users/{id}', [UserController::class, 'show'])
       ->name('users.show');

// Generate URL for named route
$url = $context->buildRouteUri('users.show', ['id' => 123]);
```

## Route Registration

Routes must be registered in your route configuration. Add your controller classes to `oz/oz_settings/oz.routes.php`:

```php
<?php

use MyApp\Controllers\UserController;
use MyApp\Controllers\PostController;

return [
    UserController::class => true,
    PostController::class => true,
    // Other route providers...
];
```

## Web vs API Routes

### Web Routes

Web routes typically return HTML responses:

```php
use OZONE\Core\Web\WebView;

class HomeController extends WebView
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/', [self::class, 'home'])->name('home');
    }
    
    public function home(RouteInfo $route)
    {
        return $this->setTemplate('home.blate')
                   ->inject(['title' => 'Welcome'])
                   ->respond();
    }
}
```

### API Routes

API routes typically return JSON responses:

```php
class ApiController extends Service
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/api/users', [self::class, 'users'])->name('api.users');
    }
    
    public function users(RouteInfo $route)
    {
        $this->json()->setData(['users' => []]);
        return $this->respond();
    }
}
```

## Route Constraints

Add constraints to route parameters:

```php
// Numeric constraint
$router->get('/users/{id}', [UserController::class, 'show'])
       ->where('id', '[0-9]+');

// Alphanumeric constraint
$router->get('/posts/{slug}', [PostController::class, 'show'])
       ->where('slug', '[a-zA-Z0-9-]+');
```

## HTTP Methods

OZone supports all standard HTTP methods:

```php
$router->get('/resource', $handler);       // GET
$router->post('/resource', $handler);      // POST
$router->put('/resource', $handler);       // PUT
$router->patch('/resource', $handler);     // PATCH
$router->delete('/resource', $handler);    // DELETE
$router->options('/resource', $handler);   // OPTIONS

// Multiple methods
$router->match(['GET', 'POST'], '/resource', $handler);

// Any method
$router->any('/resource', $handler);
```

## Route Caching

For production environments, consider caching your routes:

```php
// In your bootstrap or configuration
$router->cache(true);
```

## Error Handling

Handle route errors gracefully:

```php
class ErrorController extends Service
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/404', [self::class, 'notFound'])->name('404');
        $router->get('/500', [self::class, 'serverError'])->name('500');
    }
    
    public function notFound(RouteInfo $route)
    {
        return $this->setTemplate('errors/404.blate')
                   ->respond()
                   ->withStatus(404);
    }
}
```

## Best Practices

1. **Organize Routes**: Group related routes together
2. **Use Named Routes**: Always name your routes for easy URL generation
3. **Consistent Naming**: Use consistent naming conventions
4. **Parameter Validation**: Validate route parameters
5. **Middleware**: Use middleware for cross-cutting concerns
6. **Documentation**: Document your routes with API documentation

## Advanced Features

### Subdomain Routing

Route based on subdomains:

```php
$router->domain('api.example.com', function(Router $router) {
    $router->get('/users', [ApiController::class, 'users']);
});
```

### Route Model Binding

Automatically inject models based on route parameters:

```php
$router->get('/users/{user}', function(User $user) {
    return ['user' => $user];
});
```

## Next Steps

- Learn about [Controllers](/guide/controllers) to handle route logic
- Explore [Views](/guide/views) for rendering responses
- Check out [API Documentation](/guide/api-docs) for documenting your routes