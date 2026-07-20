<?php

declare(strict_types=1);

namespace psm\Util\Error;

use ErrorException;
use Throwable;

final class RuntimeErrorHandler
{
    private const FATAL_ERROR_TYPES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public function __construct(private readonly bool $debug = false)
    {
    }

    public static function register(bool $debug = false): self
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        $handler = new self($debug);
        set_exception_handler($handler->handleThrowable(...));
        register_shutdown_function($handler->handleShutdown(...));

        return $handler;
    }

    public function handleThrowable(Throwable $exception): never
    {
        $this->report($exception);
        exit(1);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || ($error['type'] & self::FATAL_ERROR_TYPES) === 0) {
            return;
        }

        $this->report(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    public function renderPage(string $requestId, Throwable $exception): string
    {
        $details = '';
        if ($this->debug) {
            $details = sprintf(
                '<pre>%s</pre>',
                htmlspecialchars($this->sanitize($exception::class . ': ' . $exception->getMessage()), ENT_QUOTES, 'UTF-8')
            );
        }

        $safeRequestId = htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Error de PHP Server Monitor</title>
    <style>
        :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #111827; color: #f9fafb; }
        main { width: min(42rem, calc(100% - 3rem)); padding: 2rem; border-radius: 1rem; background: #1f2937; box-shadow: 0 1rem 3rem #0006; }
        h1 { margin-top: 0; font-size: clamp(1.5rem, 4vw, 2.25rem); }
        p { line-height: 1.6; color: #d1d5db; }
        code, pre { overflow-wrap: anywhere; color: #bfdbfe; }
    </style>
</head>
<body>
<main role="alert">
    <h1>No se pudo completar la solicitud</h1>
    <p>El error fue registrado de forma segura. Use esta referencia para localizarlo en el registro de PHP:</p>
    <p><code>{$safeRequestId}</code></p>
    {$details}
</main>
</body>
</html>
HTML;
    }

    public function formatLogEntry(string $requestId, Throwable $exception): string
    {
        $entry = sprintf(
            '[%s] %s: %s in %s:%d%s%s',
            $requestId,
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL,
            $exception->getTraceAsString()
        );

        return $this->sanitize($entry);
    }

    private function report(Throwable $exception): void
    {
        $requestId = $this->newRequestId();
        error_log($this->formatLogEntry($requestId, $exception));

        if (PHP_SAPI === 'cli') {
            file_put_contents('php://stderr', "Application error: {$requestId}" . PHP_EOL);
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
        }

        echo $this->renderPage($requestId, $exception);
    }

    private function newRequestId(): string
    {
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $suffix = substr(hash('sha256', uniqid('', true)), 0, 8);
        }

        return sprintf('PSM-%s-%s', gmdate('YmdHis'), strtoupper($suffix));
    }

    private function sanitize(string $value): string
    {
        return preg_replace(
            '/\b(password|passwd|token|secret|api[_-]?key)(\s*[:=]\s*)[^\s&]+/i',
            '$1$2[REDACTED]',
            $value
        ) ?? '[Unable to sanitize error details]';
    }
}
