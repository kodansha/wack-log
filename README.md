# WACK Log

**WACK Log** is a simple WordPress plugin to output logs to the stdout.

It is created as a thin Monolog wrapper with the intention of being used with
the WACK Stack, but it can also be used with other WordPress installations,
especially convenient for those that are containerized.

## Installation

- Requires PHP 8.1 or later
- Requires WordPress 6.0 or later
- Requires Composer

### Using Composer

```bash
composer require kodansha/wack-log
```

> [!NOTE]
> This plugin is not available on the WordPress.org plugin repository.
> For the moment, the only way to install it is to use Composer.

## Usage

WACK Log exposes a global function `wack_log()` that can be used as below:

```php
wack_log()->debug('This is debug message.');
wack_log()->info('This is information message.');
wack_log()->warning('This is warning message.');
wack_log()->error('This is error message.');
```

In addition, it also provides a shorthand usage that is convenient for debugging:

```php
wack_log('This is debug message.');
```

## Configuration

There are two methods for customizing the behavior of WACK Log:

- Setting through the `WACK_LOG_SETTINGS` constant
- Setting via the settings screen in the WordPress admin menu

### Setting via WACK_LOG_SETTINGS

Define `WACK_LOG_SETTINGS` in functions.php or similar:

```php
define('WACK_LOG_SETTINGS', [
    'use_json_format' => false,
]);
```

### Setting via WordPress admin menu

Go to the settings screen of WACK Log in the WordPress admin menu and
follow the instructions on the screen.

> [!NOTE]
> If settings exist both in `WACK_LOG_SETTINGS` and the settings screen
> at the same time, the settings defined in the constant are prioritized as
> a general rule.
