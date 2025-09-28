# OZone Framework Documentation

This directory contains the documentation for the OZone Framework, built with VitePress.

## Getting Started

### Prerequisites

- Node.js 18+ 
- npm 8+

### Installation

Install documentation dependencies:

```bash
npm install
```

### Development

Start the development server:

```bash
npm run dev
```

The documentation will be available at `http://localhost:5173`

### Building

Build the documentation for production:

```bash
npm run build
```

The built files will be in `.vitepress/dist/`

### Preview

Preview the built documentation:

```bash
npm run preview
```

## Structure

```
docs/
├── .vitepress/
│   ├── config.js          # VitePress configuration
│   └── dist/              # Built documentation (ignored by git)
├── public/                # Static assets
│   └── logo.png           # OZone logo
├── guide/                 # User guide
│   ├── index.md           # Introduction
│   ├── installation.md    # Installation guide
│   ├── configuration.md   # Configuration guide
│   ├── routing.md         # Routing guide
│   ├── controllers.md     # Controllers guide
│   ├── views.md           # Views and templating guide
│   └── api-docs.md        # API documentation guide
├── api/                   # API reference
│   └── index.md           # API overview
├── examples/              # Examples and tutorials
│   └── index.md           # Code examples
├── index.md               # Homepage
└── package.json           # Documentation dependencies
```

## Contributing

To contribute to the documentation:

1. Make your changes in the appropriate markdown files
2. Test locally with `npm run dev`
3. Build and test with `npm run build && npm run preview`
4. Submit a pull request

## Writing Documentation

### Markdown Features

VitePress supports enhanced Markdown features:

- **Code Highlighting**: Use fenced code blocks with language specification
- **Custom Containers**: Use `:::tip`, `:::warning`, `:::danger` for callouts
- **Vue Components**: You can use Vue components in Markdown
- **Table of Contents**: Automatic TOC generation

### Code Examples

When adding code examples:

```php
<?php
// Always include complete, working examples
use OZONE\Core\App\Service;

class ExampleController extends Service
{
    // Implementation here
}
```

### Internal Links

Use relative links for internal navigation:

```markdown
- [Installation Guide](./installation.md)
- [API Reference](/api/)
```

## Deployment

The documentation can be deployed to any static hosting service:

- **GitHub Pages**: Use the built-in GitHub Actions workflow
- **Netlify**: Connect your repository and set build command to `npm run docs:build`
- **Vercel**: Similar to Netlify setup
- **Custom Server**: Copy the contents of `.vitepress/dist/` to your web server