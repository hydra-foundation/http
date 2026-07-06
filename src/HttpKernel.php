<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Core\Contracts\KernelInterface;
use Hydra\Http\Contracts\EmitterInterface;
use Hydra\Http\Contracts\ServerRequestProviderInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Drives the HTTP request lifecycle.
 *
 * The kernel is pure glue: capture the incoming request, hand it to the
 * application handler, and emit the response. The handler is whatever the
 * application assembled — typically a Pipeline wrapping the Router — so the
 * kernel itself knows nothing about middleware or routing.
 *
 * handle() also carries the boundary of last resort: a catch-all around the
 * whole lifecycle. It is NOT the error handler — the pipeline's
 * ErrorHandlerMiddleware remains the single authority for turning application
 * throwables into real responses (with logging, debug detail, content
 * negotiation). This catch only fires when a throwable escapes that authority
 * entirely: middleware outside it in the stack, container resolution while the
 * pipeline lazily builds itself, error rendering itself blowing up, or the
 * emitter. Without it, such a throwable surfaces as a raw PHP fatal — and
 * leaks stack traces to the client whenever display_errors is on.
 */
final class HttpKernel implements KernelInterface
{
    public function __construct(
        private readonly ServerRequestProviderInterface $requests,
        private readonly RequestHandlerInterface $handler,
        private readonly EmitterInterface $emitter,
    ) {}

    public function handle(): void
    {
        try {
            $this->emitter->emit(
                $this->handler->handle($this->requests->fromGlobals())
            );
        } catch (Throwable $e) {
            $this->panic($e);
        }
    }

    /**
     * Emit a minimal plain-text 500 for a throwable nothing else caught.
     *
     * Deliberately dependency-free: the kernel takes no logger (error_log()
     * always exists and cannot itself fail to resolve — a logger dependency
     * would just be one more thing that can break inside the last-resort
     * path) and no PSR-17 factory (building a ResponseInterface here would
     * mean trusting more machinery at the exact moment machinery has failed),
     * so it bypasses PSR-7 and writes raw headers.
     *
     * Never echoes exception details, debug flag or not: the kernel doesn't
     * know about debug — the rich rendering belongs to the pipeline's error
     * handler, which owns that decision. This path only guarantees the client
     * sees a clean 500 instead of a raw PHP fatal.
     */
    private function panic(Throwable $e): void
    {
        error_log(sprintf(
            'Uncaught %s outside the error boundary: %s in %s:%d',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo 'Internal Server Error';
    }

    public function terminate(): void
    {
    }
}
