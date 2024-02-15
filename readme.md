## Cloudflare Enterprise Cache WP Plugin

Cloudflare Enterprise Cache is a WordPress Plugin that automates managing Cloudflare cache.

## Features

- **Cache Purge Queue**: Pipes cache purges through a queue to avoid http call performance issues and rate limit of the Cloudflare API.

<!-- GETTING STARTED -->

## Getting Started

### Installation

   ```sh
   composer require spenserhale/cloudflare-enterprise-cache-wp-plugin
   ```

#### Configuration

Set up the follow filters:
- [cf/ent_cache/api_credentials](#api_credentials)
- [cf/ent_cache/process_queue](#process_queue)

### Events

To avoid tight coupling, you can use events to trigger services.

To interface with the plugin, you can use the following events:
- [cf/ent_cache/queue_many](#queue_many)
- [cf/ent_cache/purge_cache](#purge_cache)

## Hooks

### Filters

#### api_credentials

To authenticate with the Cloudflare API, you must provide your zone id, auth key, and auth email as a positional array.

Example
```php
add_filter('cf/ent_cache/api_credentials', static fn() => [$env['zone-id'], $env['auth-email'], $env['auth-key']]); 
```

#### log_level

To set the log level, you can use the `cf/ent_cache/log_level` filter. The default log level is 5, ERROR.

Levels: EMERGENCY => 8, ALERT => 7, CRITICAL => 6, ERROR => 5, WARNING => 4, NOTICE => 3, INFO => 2, DEBUG => 1

Example
```php
add_filter('cf/ent_cache/log_level', static fn() => 5); 
```

#### log

The logger does not implement a log writer. You can use the `cf/ent_cache/log` to receive log messages and write them to a file, database, or other storage.

Example
```php
add_action('cf/ent_cache/log', static function(string $level, string $message, array $context) {
    error_log(sprintf('%s: %s %s', $level, $message, json_encode($context)));
}); 
```

### Actions

#### process_queue

To process the cache purge queue, you can use the `cf/ent_cache/process_queue` action.

Example
```php
// Hook into the WordPress lifecycle
if (!wp_next_scheduled('cf/ent_cache/process_queue')) {
    wp_schedule_event(time(), $schedule, 'cf/ent_cache/process_queue');
}
```

#### queue_many

To queue items to cache purge, you can use the `cf/ent_cache/queue_many` action.

Example
```php
do_action('cf/ent_cache/queue_many', [
    ['type' => 'file', 'content' => 'https://www.exampledomain.com/page'],
    ['type' => 'tag', 'content' => 'post:1'],
]);
```

#### purge_cache

To immediately purge items, you can use the `cf/ent_cache/purge_cache` action.

Example
```php
$response = apply_filters('cf/ent_cache/purge_cache', [
    ['type' => 'host', 'content' => 'www.exampledomain.com'],
    ['type' => 'tag', 'content' => 'site:2'],
]);
```

<!-- ROADMAP -->
## Roadmap

- [ ] Automatic Cache Key Generation
- [ ] Automatic Cache Purge on Content Update

## Tests

Then run the tests:
   ```sh
    composer test
   ```

## License
The Cloudflare Enterprise Cache WP Plugin is open-sourced software licensed under the [GNU General Public License v3.0 or later](https://spdx.org/licenses/GPL-3.0-or-later.html).

<p align="right">(<a href="#readme-top">back to top</a>)</p>

<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->

[product-screenshot]: images/explainer.png
