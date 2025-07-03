# Vectorify package for Laravel

Vectorify is the end-to-end AI connector for Laravel, letting you query and explore your data in natural language in seconds.

Laravel is famous for turning complex web app chores into elegant, artisan-friendly code. Vectorify brings that same spirit to AI-powered data exploration: with one `composer install` and a single `config` file, your Laravel app becomes a conversational knowledge base you (and your customers) can query in natural language.

<div align="center">
    <img alt="vectorify setup" src="https://vectorify.ai/packages/vectorify-laravel/vectorify-setup.svg">
    <br/><br/>
</div>

To interact with your data, you have four primary methods to choose from:

1. Use the [Chats](https://app.vectorify.ai/) page within our platform (fastest)
2. Embed the [Chatbot](https://docs.vectorify.ai/project/chatbot) into your Laravel app (turn data querying into a product feature)
3. Add the [MCP](https://docs.vectorify.ai/mcp-server) server to ChatGPT, Claude, etc. (use your data anywhere you work)
4. Call the REST [API](https://docs.vectorify.ai/api-reference) endpoints (build custom integrations and workflows)

Unlike text-to-SQL tools that expose your entire database and take 30+ seconds per query, Vectorify uses proven RAG technology to deliver accurate answers in <4 seconds while keeping your database secure. Head to our [blog](https://vectorify.ai/blog/vectorify-laravel-unlock-ai-ready-data-in-60-seconds) to learn more about Vectorify.

This package provides seamless integration to automatically extract, transform, and upsert your Laravel application data to Vectorify.

## Requirements

- PHP 8.2 or higher
- Laravel 10 or higher

## Installation

Install the package via Composer:

```bash
composer require vectorifyai/vectorify-laravel
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

This approach uses the model's `$fillable` or a custom `$vectorify` property as the column list. Read the [documentation](https://docs.vectorify.ai/configuration) to learn more about defining the collections.

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
