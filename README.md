# WACK Log

**WACK Log** is a simple WordPress plugin to output logs to the stdout.

It is created as a thin Monolog wrapper with the intention of being used with
the WACK Stack, but it can also be used with other WordPress installations,
especially convenient for those that are containerized.

## Installation

- Requires PHP 8.2 or later
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

WACK Log exposes a global function `logger()` that can be used as below:

```php
logger()->debug('This is debug message.');
logger()->info('This is information message.');
logger()->warning('This is warning message.');
logger()->error('This is error message.');
```

In addition, it also provides a shorthand usage that is convenient for debugging:

```php
logger('This is debug message.');
```

Each method also accepts a context array as the second argument:

```php
logger()->info('User logged in.', ['user_id' => $user->ID]);
```

## Capturing PHP errors

When the `capture_php_errors` setting is enabled, WACK Log replaces PHP's
standard error log, so that PHP errors and WordPress debug messages are also
output through WACK Log (in JSON if `use_json_format` is enabled):

| Source | Log level | Notes |
|---|---|---|
| Warnings (`E_WARNING`, `E_USER_WARNING`, ...) | `WARNING` | Not passed to PHP's standard error log |
| Notices and deprecations (`E_NOTICE`, `E_DEPRECATED`, ...) | `NOTICE` | Not passed to PHP's standard error log |
| Fatal errors and uncaught exceptions | `CRITICAL` | Logged on shutdown, or right before WordPress shows its critical error page |
| `_doing_it_wrong()`, `_deprecated_function()` and other `_deprecated_*()` (only when `WP_DEBUG` is `true`) | `NOTICE` | Logged with structured context, and their `trigger_error()` calls are suppressed to avoid duplicates |

```php
define('WACK_LOG_SETTINGS', [
    'use_json_format' => true,
    'capture_php_errors' => true,
]);
```

> [!NOTE]
> Please be aware of the following limitations:
>
> - `error_reporting()` and the `@` operator are respected, including for fatal
>   errors and WordPress debug messages (which are treated as `E_USER_NOTICE` for
>   `_doing_it_wrong()` and `E_USER_DEPRECATED` for `_deprecated_*()`).
> - Errors raised before WACK Log is loaded (e.g. in `wp-config.php`,
>   WordPress core bootstrap or must-use plugins) are not captured.
> - Messages passed directly to `error_log()` are not captured.
> - Fatal errors are also written to PHP's standard error log because PHP
>   itself handles them. Set `log_errors` to `Off` if you don't want them.
> - When the memory limit is exceeded, `memory_limit` is raised by 8 MiB on
>   shutdown so that the error can still be logged.
> - Since non-fatal errors are no longer passed to PHP's standard error handler,
>   they are not written to `wp-content/debug.log` or displayed on the screen
>   even if `WP_DEBUG_LOG` / `WP_DEBUG_DISPLAY` are enabled.
>   If another error handler (e.g. an error tracking service) was registered
>   before WACK Log, it is still called, but captured errors are not passed to
>   PHP's standard error handler even if it returns `false`.
>   Note that it is called for every error type even if it was registered for
>   specific types only, as PHP provides no way to know them.
> - Uncaught exceptions are not handled by an exception handler, so that
>   WordPress' fatal error handler and recovery mode keep working.

## Configuration

There are two methods for customizing the behavior of WACK Log:

- Setting through the `WACK_LOG_SETTINGS` constant
- Setting via the settings screen in the WordPress admin menu

### Setting via WACK_LOG_SETTINGS

Define `WACK_LOG_SETTINGS` in `wp-config.php` (or `config/application.php` for Bedrock):

```php
define('WACK_LOG_SETTINGS', [
    'use_json_format' => true,
    'capture_php_errors' => true,
]);
```

| Key | Default | Description |
|---|---|---|
| `use_json_format` | `false` | Output logs in JSON format |
| `capture_php_errors` | `false` | Capture PHP errors and WordPress debug messages (see [Capturing PHP errors](#capturing-php-errors)) |

> [!IMPORTANT]
> `capture_php_errors` is read when WACK Log is loaded, so the constant must be
> defined before plugins are loaded. Defining it in a theme's `functions.php`
> has no effect on `capture_php_errors`.

### Setting via WordPress admin menu

Go to the settings screen of WACK Log in the WordPress admin menu and
follow the instructions on the screen.

> [!NOTE]
> If settings exist both in `WACK_LOG_SETTINGS` and the admin menu settings
> at the same time, the settings defined in the constant are prioritized as
> a general rule.
