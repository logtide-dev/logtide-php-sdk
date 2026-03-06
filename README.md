<p align="center">
  <img src="https://raw.githubusercontent.com/logtide-dev/logtide/main/docs/images/logo.png" alt="LogTide Logo" width="400">
</p>

<h1 align="center">logtide/logtide</h1>

<p align="center">
  <a href="https://packagist.org/packages/logtide/logtide"><img src="https://img.shields.io/packagist/v/logtide/logtide?color=blue" alt="Packagist"></a>
  <a href="../../LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg" alt="License"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.1+-purple.svg" alt="PHP"></a>
</p>

<p align="center">
  Core client, hub, transports, and utilities for the <a href="https://logtide.dev">LogTide</a> PHP SDK ecosystem.
</p>

---

## Features

- **Client** - capture logs, errors, breadcrumbs, and spans
- **Hub** - global singleton for convenient access across your app
- **Scope** - per-request context isolation with tags, extras, and breadcrumbs
- **BatchTransport** - automatic batching with retry logic and circuit breaker
- **Distributed tracing** - W3C Trace Context (`traceparent`) propagation
- **Monolog integration** - `LogtideHandler` and `BreadcrumbHandler`
- **PSR-15 middleware** - generic HTTP request tracing
- **Built-in integrations** - error/exception listeners, request and environment capture

## Installation

```bash
composer require logtide/logtide
```

> **Note:** You typically don't need to install this package directly. Use a framework-specific package like `logtide/logtide-laravel`, `logtide/logtide-symfony`, etc. which include `logtide/logtide` as a dependency.

---

## Quick Start

### Using global helper functions (recommended)

```php
\LogTide\init([
    'dsn' => 'https://lp_your_key@your-instance.com',
    // Or use api_url + api_key instead of dsn:
    // 'api_url' => 'https://your-instance.com',
    // 'api_key' => 'lp_your_key',
    'service' => 'my-app',
]);

// Log messages
\LogTide\info('Server started', ['port' => 8080]);
\LogTide\error('Payment failed', ['order_id' => 456]);

// Capture exceptions
try {
    dangerousOperation();
} catch (\Throwable $e) {
    \LogTide\captureException($e);
}

// Add breadcrumbs
\LogTide\addBreadcrumb(new \LogTide\Breadcrumb\Breadcrumb(
    \LogTide\Enum\BreadcrumbType::HTTP,
    'GET /api/users',
    category: 'http.request',
));

// Graceful shutdown (automatic via register_shutdown_function)
\LogTide\flush();
```

### Using the Hub directly

```php
use LogTide\LogtideSdk;
use LogTide\Enum\LogLevel;

$hub = LogtideSdk::init([
    'dsn' => 'https://lp_your_key@your-instance.com',
    'service' => 'my-app',
]);

// Log messages
$hub->captureLog(LogLevel::INFO, 'Request handled', ['user_id' => 123]);

// Scoped context
$hub->configureScope(function ($scope) {
    $scope->setTag('request_id', 'abc-123');
    $scope->setUser(['id' => 42]);
});

// Isolated scope
$hub->withScope(function () use ($hub) {
    $hub->getScope()->setTag('temp', 'value');
    $hub->captureLog(LogLevel::DEBUG, 'Scoped log');
});
```

---

## Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `dsn` | `string` | - | DSN string: `https://lp_KEY@host` |
| `api_url` | `string` | - | API URL (alternative to DSN) |
| `api_key` | `string` | - | API key (alternative to DSN) |
| `service` | `string` | `'unknown'` | Service name for log attribution |
| `environment` | `string` | `null` | Environment (e.g. `production`, `staging`) |
| `release` | `string` | `null` | Release / version identifier |
| `batch_size` | `int` | `100` | Logs to batch before sending |
| `flush_interval` | `int` | `5000` | Auto-flush interval in ms |
| `max_buffer_size` | `int` | `10000` | Max logs in buffer before dropping |
| `max_retries` | `int` | `3` | Max retry attempts on failure |
| `retry_delay_ms` | `int` | `1000` | Initial retry delay (exponential backoff) |
| `circuit_breaker_threshold` | `int` | `5` | Failures before opening circuit |
| `circuit_breaker_reset_ms` | `int` | `30000` | Time before retrying after circuit opens |
| `max_breadcrumbs` | `int` | `100` | Max breadcrumbs to keep |
| `traces_sample_rate` | `float` | `1.0` | Sample rate for traces (0.0 to 1.0) |
| `debug` | `bool` | `false` | Enable debug logging |
| `attach_stacktrace` | `bool` | `false` | Attach stack traces to log entries |
| `send_default_pii` | `bool` | `false` | Send personally identifiable information |
| `transport` | `TransportInterface` | `null` | Custom transport (overrides default) |
| `integrations` | `array\|Closure` | `null` | Integrations to install |
| `before_send` | `Closure` | `null` | Modify or drop events before sending |
| `before_breadcrumb` | `Closure` | `null` | Modify or drop breadcrumbs |
| `ignore_exceptions` | `string[]` | `[]` | Exception classes to ignore |
| `tags` | `array` | `[]` | Global tags for all events |
| `global_metadata` | `array` | `[]` | Global metadata for all events |

---

## Distributed Tracing

```php
use LogTide\Tracing\PropagationContext;

// Parse incoming traceparent header
$traceparent = $request->getHeaderLine('traceparent');
\LogTide\continueTrace($traceparent);

// Start a span
$span = \LogTide\startSpan('db.query', [
    'kind' => \LogTide\Enum\SpanKind::CLIENT,
]);

// ... do work ...

// Finish the span
\LogTide\finishSpan($span);

// Get traceparent for outgoing requests
$outgoingHeader = \LogTide\getTraceparent();
```

---

## Monolog Integration

```php
use Monolog\Logger;
use LogTide\Monolog\LogtideHandler;
use LogTide\Monolog\BreadcrumbHandler;

$logger = new Logger('app');

// Send logs to LogTide
$logger->pushHandler(new LogtideHandler());

// Record logs as breadcrumbs
$logger->pushHandler(new BreadcrumbHandler());

$logger->info('Hello from Monolog!');
```

---

## License

MIT License - see [LICENSE](../../LICENSE) for details.

## Links

- [LogTide Website](https://logtide.dev)
- [Documentation](https://logtide.dev/docs)
- [GitHub](https://github.com/logtide-dev/logtide-php)
