# Controllers

Learn how to create and organize controllers in your OZone application.

## Overview

Controllers in OZone are classes that handle the business logic of your application. They process incoming requests, interact with data sources, and return appropriate responses.

## Basic Controllers

### Service Controller

Most controllers extend the `Service` base class:

```php
<?php

use OZONE\Core\App\Service;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteInfo;

class UserController extends Service
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/users', [self::class, 'index'])->name('users.index');
        $router->get('/users/{id}', [self::class, 'show'])->name('users.show');
        $router->post('/users', [self::class, 'store'])->name('users.store');
        $router->put('/users/{id}', [self::class, 'update'])->name('users.update');
        $router->delete('/users/{id}', [self::class, 'destroy'])->name('users.destroy');
    }
    
    public function index(RouteInfo $route)
    {
        $users = $this->getAllUsers();
        
        $this->json()->setData([
            'users' => $users,
            'total' => count($users)
        ]);
        
        return $this->respond();
    }
    
    public function show(RouteInfo $route)
    {
        $id = $route->getParam('id');
        $user = $this->getUserById($id);
        
        if (!$user) {
            return $this->notFound('User not found');
        }
        
        $this->json()->setData(['user' => $user]);
        return $this->respond();
    }
    
    public function store(RouteInfo $route)
    {
        $data = $route->getRequest()->getParsedBody();
        
        // Validate data
        $validation = $this->validateUserData($data);
        if (!$validation['valid']) {
            return $this->badRequest($validation['errors']);
        }
        
        $user = $this->createUser($data);
        
        $this->json()->setData(['user' => $user]);
        return $this->respond()->withStatus(201);
    }
    
    public function update(RouteInfo $route)
    {
        $id = $route->getParam('id');
        $data = $route->getRequest()->getParsedBody();
        
        $user = $this->getUserById($id);
        if (!$user) {
            return $this->notFound('User not found');
        }
        
        $updatedUser = $this->updateUser($id, $data);
        
        $this->json()->setData(['user' => $updatedUser]);
        return $this->respond();
    }
    
    public function destroy(RouteInfo $route)
    {
        $id = $route->getParam('id');
        
        $deleted = $this->deleteUser($id);
        if (!$deleted) {
            return $this->notFound('User not found');
        }
        
        return $this->respond()->withStatus(204);
    }
    
    // Helper methods
    private function getAllUsers(): array
    {
        // Implement user retrieval logic
        return [];
    }
    
    private function getUserById(int $id): ?array
    {
        // Implement user retrieval by ID
        return null;
    }
    
    private function createUser(array $data): array
    {
        // Implement user creation logic
        return $data + ['id' => rand(1, 1000)];
    }
    
    private function updateUser(int $id, array $data): array
    {
        // Implement user update logic
        return $data + ['id' => $id];
    }
    
    private function deleteUser(int $id): bool
    {
        // Implement user deletion logic
        return true;
    }
    
    private function validateUserData(array $data): array
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors[] = 'Name is required';
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    private function notFound(string $message)
    {
        $this->json()->setData(['error' => $message]);
        return $this->respond()->withStatus(404);
    }
    
    private function badRequest($errors)
    {
        $this->json()->setData(['errors' => $errors]);
        return $this->respond()->withStatus(400);
    }
}
```

### Web Controller

For web applications, you might extend `WebView`:

```php
<?php

use OZONE\Core\Web\WebView;
use OZONE\Core\Router\Router;
use OZONE\Core\Router\RouteInfo;

class HomeController extends WebView
{
    public static function registerRoutes(Router $router): void
    {
        $router->get('/', [self::class, 'index'])->name('home');
        $router->get('/about', [self::class, 'about'])->name('about');
        $router->get('/contact', [self::class, 'contact'])->name('contact');
    }
    
    public function index(RouteInfo $route)
    {
        $stats = $this->getAppStats();
        
        return $this->setTemplate('home/index.blate')
                   ->inject([
                       'title' => 'Welcome to OZone',
                       'stats' => $stats,
                       'featured_posts' => $this->getFeaturedPosts()
                   ])
                   ->respond();
    }
    
    public function about(RouteInfo $route)
    {
        return $this->setTemplate('home/about.blate')
                   ->inject([
                       'title' => 'About Us',
                       'team_members' => $this->getTeamMembers()
                   ])
                   ->respond();
    }
    
    public function contact(RouteInfo $route)
    {
        if ($route->getRequest()->getMethod() === 'POST') {
            return $this->handleContactForm($route);
        }
        
        return $this->setTemplate('home/contact.blate')
                   ->inject(['title' => 'Contact Us'])
                   ->respond();
    }
    
    private function handleContactForm(RouteInfo $route)
    {
        $data = $route->getRequest()->getParsedBody();
        
        // Process contact form
        $success = $this->sendContactEmail($data);
        
        return $this->setTemplate('home/contact.blate')
                   ->inject([
                       'title' => 'Contact Us',
                       'message' => $success ? 'Message sent successfully!' : 'Failed to send message.',
                       'message_type' => $success ? 'success' : 'error'
                   ])
                   ->respond();
    }
}
```

## Request Handling

### Accessing Request Data

```php
public function handleRequest(RouteInfo $route)
{
    $request = $route->getRequest();
    
    // Get request method
    $method = $request->getMethod();
    
    // Get query parameters
    $query = $request->getQueryParams();
    $page = $query['page'] ?? 1;
    
    // Get POST data
    $body = $request->getParsedBody();
    $name = $body['name'] ?? null;
    
    // Get headers
    $headers = $request->getHeaders();
    $authHeader = $request->getHeaderLine('Authorization');
    
    // Get route parameters
    $id = $route->getParam('id');
    $params = $route->getParams();
    
    // Get uploaded files
    $files = $request->getUploadedFiles();
}
```

### Input Validation

```php
private function validateInput(array $data, array $rules): array
{
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        if ($rule['required'] && empty($value)) {
            $errors[$field][] = "{$field} is required";
        }
        
        if (!empty($value) && isset($rule['type'])) {
            switch ($rule['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "Invalid email format";
                    }
                    break;
                    
                case 'integer':
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "Must be an integer";
                    }
                    break;
                    
                case 'string':
                    if (!is_string($value)) {
                        $errors[$field][] = "Must be a string";
                    }
                    break;
            }
        }
        
        if (!empty($value) && isset($rule['min_length'])) {
            if (strlen($value) < $rule['min_length']) {
                $errors[$field][] = "Must be at least {$rule['min_length']} characters";
            }
        }
    }
    
    return $errors;
}

public function store(RouteInfo $route)
{
    $data = $route->getRequest()->getParsedBody();
    
    $rules = [
        'name' => ['required' => true, 'type' => 'string', 'min_length' => 2],
        'email' => ['required' => true, 'type' => 'email'],
        'age' => ['required' => false, 'type' => 'integer']
    ];
    
    $errors = $this->validateInput($data, $rules);
    
    if (!empty($errors)) {
        $this->json()->setData(['errors' => $errors]);
        return $this->respond()->withStatus(422);
    }
    
    // Process valid data
}
```

## Response Handling

### JSON Responses

```php
public function apiResponse(RouteInfo $route)
{
    // Success response
    $this->json()->setData([
        'success' => true,
        'data' => $data,
        'message' => 'Operation completed successfully'
    ]);
    return $this->respond();
    
    // Error response
    $this->json()->setData([
        'success' => false,
        'error' => 'Something went wrong',
        'code' => 'INTERNAL_ERROR'
    ]);
    return $this->respond()->withStatus(500);
    
    // Paginated response
    $this->json()->setData([
        'data' => $items,
        'meta' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ]
    ]);
    return $this->respond();
}
```

### HTML Responses

```php
public function webResponse(RouteInfo $route)
{
    return $this->setTemplate('page.blate')
               ->inject([
                   'title' => 'Page Title',
                   'content' => $content
               ])
               ->respond();
}
```

### Custom Responses

```php
public function downloadFile(RouteInfo $route)
{
    $file = $this->getFile($route->getParam('id'));
    
    if (!$file) {
        return $this->respond()->withStatus(404);
    }
    
    return $this->getContext()
               ->getResponse()
               ->withHeader('Content-Type', $file['mime_type'])
               ->withHeader('Content-Disposition', 'attachment; filename="' . $file['name'] . '"')
               ->withBody($file['content']);
}

public function redirect(RouteInfo $route)
{
    $url = $this->getContext()->buildRouteUri('target.route');
    
    return $this->getContext()
               ->getResponse()
               ->withStatus(302)
               ->withHeader('Location', $url);
}
```

## Authentication & Authorization

### Authentication

```php
class AuthController extends Service
{
    public function login(RouteInfo $route)
    {
        $data = $route->getRequest()->getParsedBody();
        
        $user = $this->authenticate($data['email'], $data['password']);
        
        if (!$user) {
            $this->json()->setData(['error' => 'Invalid credentials']);
            return $this->respond()->withStatus(401);
        }
        
        $token = $this->generateToken($user);
        
        $this->json()->setData([
            'user' => $user,
            'token' => $token
        ]);
        
        return $this->respond();
    }
    
    private function authenticate(string $email, string $password): ?array
    {
        // Implement authentication logic
        return null;
    }
    
    private function generateToken(array $user): string
    {
        // Generate JWT or session token
        return 'token_' . $user['id'];
    }
}
```

### Authorization

```php
class ProtectedController extends Service
{
    public function protectedAction(RouteInfo $route)
    {
        $user = $this->getCurrentUser($route);
        
        if (!$user) {
            return $this->unauthorized();
        }
        
        if (!$this->canAccess($user, 'admin')) {
            return $this->forbidden();
        }
        
        // Proceed with protected action
        $this->json()->setData(['message' => 'Access granted']);
        return $this->respond();
    }
    
    private function getCurrentUser(RouteInfo $route): ?array
    {
        $token = $route->getRequest()->getHeaderLine('Authorization');
        // Validate token and return user
        return null;
    }
    
    private function canAccess(array $user, string $permission): bool
    {
        // Check user permissions
        return in_array($permission, $user['permissions'] ?? []);
    }
    
    private function unauthorized()
    {
        $this->json()->setData(['error' => 'Authentication required']);
        return $this->respond()->withStatus(401);
    }
    
    private function forbidden()
    {
        $this->json()->setData(['error' => 'Insufficient permissions']);
        return $this->respond()->withStatus(403);
    }
}
```

## Error Handling

### Exception Handling

```php
public function safeAction(RouteInfo $route)
{
    try {
        $result = $this->performRiskyOperation();
        
        $this->json()->setData(['result' => $result]);
        return $this->respond();
        
    } catch (ValidationException $e) {
        $this->json()->setData([
            'error' => 'Validation failed',
            'details' => $e->getErrors()
        ]);
        return $this->respond()->withStatus(422);
        
    } catch (NotFoundException $e) {
        $this->json()->setData(['error' => $e->getMessage()]);
        return $this->respond()->withStatus(404);
        
    } catch (Exception $e) {
        // Log the error
        error_log($e->getMessage());
        
        $this->json()->setData(['error' => 'Internal server error']);
        return $this->respond()->withStatus(500);
    }
}
```

## Best Practices

1. **Single Responsibility**: Each controller should handle a specific domain
2. **Thin Controllers**: Keep business logic in service classes
3. **Consistent Naming**: Use consistent naming conventions
4. **Input Validation**: Always validate input data
5. **Error Handling**: Handle errors gracefully
6. **Authentication**: Properly authenticate and authorize users
7. **Documentation**: Document your controller methods
8. **Testing**: Write tests for your controllers

## Organizing Controllers

### Directory Structure

```
controllers/
├── Api/
│   ├── V1/
│   │   ├── UserController.php
│   │   └── PostController.php
│   └── V2/
│       └── UserController.php
├── Web/
│   ├── HomeController.php
│   └── AuthController.php
└── Admin/
    ├── DashboardController.php
    └── UsersController.php
```

### Namespacing

```php
<?php

namespace MyApp\Controllers\Api\V1;

use OZONE\Core\App\Service;

class UserController extends Service
{
    // Controller implementation
}
```

## Next Steps

- Learn about [Routing](/guide/routing) to connect URLs to controllers
- Explore [Views](/guide/views) for rendering responses
- Check out [API Documentation](/guide/api-docs) for documenting your endpoints