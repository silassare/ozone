# Views

Learn how to create and manage views in your OZone application.

## Overview

OZone provides a powerful view system for rendering templates and returning responses. The framework includes the Blate templating engine for dynamic content rendering.

## WebView Class

The `WebView` class is the base class for all web views:

```php
<?php

use OZONE\Core\Web\WebView;
use OZONE\Core\Router\RouteInfo;

class MyView extends WebView
{
    public function render(RouteInfo $route)
    {
        return $this->setTemplate('my-template.blate')
                   ->inject(['title' => 'My Page'])
                   ->respond();
    }
}
```

## Template System

### Blate Templates

OZone uses the Blate templating engine for dynamic content:

```html
<!-- my-template.blate -->
<!DOCTYPE html>
<html lang="{oz.getLanguage()}">
<head>
    <title>{title}</title>
</head>
<body>
    <h1>{title}</h1>
    <p>Welcome to {oz.getAppName()}!</p>
    
    {@if users}
    <ul>
        {@each users as user}
        <li>{user.name} - {user.email}</li>
        {/each}
    </ul>
    {@else}
    <p>No users found.</p>
    {/if}
</body>
</html>
```

### Template Location

Templates are located using the `Templates` class:

```php
use OZONE\Core\FS\Templates;

// Add custom template directory
Templates::addSource('/path/to/templates');

// Compile template
$output = Templates::compile('template.blate', $data);
```

### Template Paths

OZone supports different template path prefixes:

- `oz://template.blate` - Search in all sources (project, plugins, core)
- `oz://~project~/template.blate` - Search only in project templates
- `oz://~core~/template.blate` - Search only in core templates

## Data Injection

### Injecting Data

Inject data into your templates:

```php
class UserView extends WebView
{
    public function profile(RouteInfo $route)
    {
        $user = $this->getUserById($route->getParam('id'));
        
        return $this->setTemplate('user/profile.blate')
                   ->inject([
                       'user' => $user,
                       'title' => 'User Profile',
                       'meta' => ['description' => 'Profile for ' . $user['name']]
                   ])
                   ->respond();
    }
    
    public function list(RouteInfo $route)
    {
        $users = $this->getAllUsers();
        
        return $this->setTemplate('user/list.blate')
                   ->injectKey('users', $users)
                   ->injectKey('total', count($users))
                   ->respond();
    }
}
```

### Global Data

Access global data in templates:

```php
// In template
{oz.getAppName()}        // Application name
{oz.getLanguage()}       // Current language
{context.getUser()}      // Current user
{url('/path')}          // Build URL
{route('route.name')}   // Build named route URL
{i18n('key')}          // Internationalization
```

## Template Inheritance

### Layout Templates

Create base layouts for consistent design:

```html
<!-- layouts/app.blate -->
<!DOCTYPE html>
<html lang="{oz.getLanguage()}">
<head>
    <title>{@block title}Default Title{/block} - {oz.getAppName()}</title>
    {@block head}{/block}
</head>
<body>
    <header>
        {@include 'partials/header.blate'}
    </header>
    
    <main>
        {@block content}
        <p>Default content</p>
        {/block}
    </main>
    
    <footer>
        {@include 'partials/footer.blate'}
    </footer>
    
    {@block scripts}{/block}
</body>
</html>
```

### Extending Layouts

Extend base layouts in your templates:

```html
<!-- user/profile.blate -->
{@extends 'layouts/app.blate'}

{@block title}User Profile{/block}

{@block head}
<meta name="description" content="Profile for {user.name}">
{/block}

{@block content}
<div class="user-profile">
    <h1>{user.name}</h1>
    <p>Email: {user.email}</p>
    <p>Joined: {user.created_at}</p>
</div>
{/block}

{@block scripts}
<script src="/js/user-profile.js"></script>
{/block}
```

## Response Types

### HTML Response

Return HTML responses for web pages:

```php
public function page(RouteInfo $route)
{
    return $this->setTemplate('page.blate')
               ->inject(['content' => 'Page content'])
               ->respond();
}
```

### JSON Response

Return JSON responses for APIs:

```php
public function api(RouteInfo $route)
{
    $this->json()
         ->setData(['message' => 'Success', 'data' => $data]);
    
    return $this->respond();
}
```

### Custom Responses

Create custom response types:

```php
public function download(RouteInfo $route)
{
    $response = $this->getContext()->getResponse();
    
    return $response->withHeader('Content-Type', 'application/pdf')
                   ->withHeader('Content-Disposition', 'attachment; filename="file.pdf"')
                   ->withBody($pdfContent);
}
```

## View Helpers

### Built-in Helpers

OZone provides several built-in helpers:

```php
// In templates
{url('/path')}                    // Build URL
{route('route.name', params)}     // Build named route
{setting('key', 'default')}       // Get configuration value
{i18n('translation.key')}         // Get translated text
```

### Custom Helpers

Register custom template helpers:

```php
use Blate\Blate;

Blate::registerHelper('formatDate', function($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
});

// Use in templates
{formatDate(user.created_at, 'F j, Y')}
```

## Error Views

### Error Templates

Create templates for error pages:

```html
<!-- errors/404.blate -->
{@extends 'layouts/app.blate'}

{@block title}Page Not Found{/block}

{@block content}
<div class="error-page">
    <h1>404 - Page Not Found</h1>
    <p>The page you requested could not be found.</p>
    <a href="{route('home')}">Return to Homepage</a>
</div>
{/block}
```

### Error Handling

Handle errors in views:

```php
class ErrorView extends WebView
{
    public function notFound(RouteInfo $route)
    {
        return $this->setTemplate('errors/404.blate')
                   ->respond()
                   ->withStatus(404);
    }
    
    public function serverError(RouteInfo $route, $exception = null)
    {
        return $this->setTemplate('errors/500.blate')
                   ->inject(['error' => $exception])
                   ->respond()
                   ->withStatus(500);
    }
}
```

## Performance

### Template Caching

Enable template caching for production:

```php
// In configuration
return [
    'template_cache' => true,
    'cache_dir' => '/var/cache/templates'
];
```

### View Caching

Cache view output for frequently accessed pages:

```php
public function expensiveView(RouteInfo $route)
{
    $cacheKey = 'view_cache_' . $route->getName();
    
    return $this->cache($cacheKey, 3600, function() use ($route) {
        return $this->setTemplate('expensive.blate')
                   ->inject($this->getExpensiveData())
                   ->render();
    });
}
```

## Best Practices

1. **Organize Templates**: Use a logical directory structure
2. **Use Layouts**: Create consistent layouts for your application
3. **Separate Concerns**: Keep logic out of templates
4. **Cache Templates**: Enable caching in production
5. **Escape Data**: Always escape user input in templates
6. **Consistent Naming**: Use consistent naming for templates and views

## Advanced Features

### Partial Views

Include partial templates:

```html
<!-- Include a partial -->
{@include 'partials/user-card.blate' with user}

<!-- Partial template: partials/user-card.blate -->
<div class="user-card">
    <h3>{name}</h3>
    <p>{email}</p>
</div>
```

### Conditional Rendering

Use conditional logic in templates:

```html
{@if user.isAdmin()}
<div class="admin-panel">
    <a href="{route('admin.dashboard')}">Admin Dashboard</a>
</div>
{@elseif user.isModerator()}
<div class="mod-panel">
    <a href="{route('mod.dashboard')}">Moderator Panel</a>
</div>
{@else}
<div class="user-panel">
    <a href="{route('user.profile')}">My Profile</a>
</div>
{/if}
```

### Loops and Iteration

Iterate over collections:

```html
{@each posts as post}
<article>
    <h2>{post.title}</h2>
    <p>{post.excerpt}</p>
    <time>{formatDate(post.published_at)}</time>
</article>
{@empty}
<p>No posts found.</p>
{/each}
```

## Next Steps

- Learn about [Controllers](/guide/controllers) to handle view logic
- Explore [Routing](/guide/routing) to connect URLs to views
- Check out [API Documentation](/guide/api-docs) for API-specific views