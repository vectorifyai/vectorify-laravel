# Vectorify package for Laravel

Vectorify is the end-to-end AI connector for Laravel, letting you query and explore your data in natural language in seconds.

This package provides seamless integration to automatically extract, transform, and upsert your Laravel application data to Vectorify.

## Requirements

- PHP 8.2 or higher
- Laravel 10 or higher

## Installation

Install the package via Composer:

```bash
composer require vectorifyai/laravel-vectorify
```

The package automatically registers itself with Laravel through package auto-discovery.

## Configuration

### 1. Publish configuration file

Publish the configuration file to define your collections:

```bash
php artisan vendor:publish --tag=vectorify
```

This will create a `config/vectorify.php` file in your application.

### 2. Environment variables

Add the API Key to your `.env` file:

```env
VECTORIFY_API_KEY=your_api_key_here
```

You can get your API Key from Vectorify's [dashboard](https://app.vectorify.ai).

### 3. Configure collections

Edit the `config/vectorify.php` file to define which models (collections) and columns you want to upsert. The simplest collection configuration references a model class:

```php
'collections' => [
    \App\Models\Invoice::class,
]
```

This approach uses the model's `$fillable` properties or a custom `$vectorify` property as the column list. Read the [documentation](https://docs.vectorify.ai/configuration) to learn more how to define the collections.

## Upsert

### Manual synchronisation

Run the upsert command manually to sync your data:

```bash
php artisan vectorify:upsert
```

### Automatic synchronisation

The package automatically schedules the upsert command to run every 6 hours.

## Changelog

Please see [Releases](../../releases) for more information on what has changed recently.

## Contributing

Pull requests are more than welcome. You must follow the PSR coding standards.

## Security

Please review [our security policy](https://github.com/vectorifyai/laravel-vectorify/security/policy) on how to report security vulnerabilities.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.
