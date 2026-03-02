# Installation

This guide will help you install and set up OZone framework for your project.

## Requirements

Before installing OZone, make sure your system meets the following requirements:

- PHP 8.1 or higher
- Composer
- Required PHP extensions:
  - ext-pdo
  - ext-openssl
  - ext-json
  - ext-gd
  - ext-libxml
  - ext-simplexml
  - ext-posix
  - ext-bcmath
  - ext-fileinfo

## Installation via Composer

The recommended way to install OZone is through Composer:

```bash
composer require silassare/ozone
```

## Creating a New Project

You can create a new OZone project using the following command:

```bash
composer create-project silassare/ozone my-ozone-app
```

This will create a new directory `my-ozone-app` with a basic OZone application structure.

## Manual Installation

If you prefer to install OZone manually:

1. Download the latest release from GitHub
2. Extract the files to your project directory
3. Run `composer install` to install dependencies

## Directory Structure

After installation, your project will have the following structure:

```
my-ozone-app/
├── oz/                 # Framework core files
├── conf/              # Configuration files
├── bin/               # Command-line tools
├── tests/             # Test files
├── vendor/            # Composer dependencies
├── composer.json      # Project dependencies
└── README.md          # Project documentation
```

## Configuration

After installation, you'll need to configure your application. See the [Configuration Guide](/guide/configuration) for detailed instructions.

## Verification

To verify your installation is working correctly, you can run:

```bash
./bin/oz --version
```

This should display the OZone version information.

## What's Next?

Now that you have OZone installed, you can:

- Read the [Configuration Guide](/guide/configuration) to set up your application
- Learn about [Routing](/guide/routing) to create your first endpoints
- Explore [API Documentation](/guide/api-docs) features