<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Exceptions\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Outermost middleware and the single authority that turns errors into
 * responses, so every failure in the app gets a consistent shape.
 *
 * An {@see HttpException} is an intentional, mapped error: it carries its own
 * status and headers (e.g. a 404, or a 405 with its Allow list). Any other
 * Throwable is an unexpected fault and becomes a 500, so a controller bug never
 * leaks a raw fatal to the client.
 *
 * Faults (5xx, including every non-HttpException) are forwarded to a PSR-3
 * logger at error level with the exception under the conventional 'exception'
 * context key; expected client errors (4xx) are not logged as faults. The
 * logger defaults to a NullLogger, so logging is opt-in and the catch path
 * needs no null checks.
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Responder $responder,
        private readonly bool $debug = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (HttpException $e) {
            return $this->render($e, $e->status(), $e->headers());
        } catch (Throwable $e) {
            return $this->render($e, 500);
        }
    }

    /**
     * Log the error if it is a fault (5xx), then build its response.
     *
     * @param array<string, string> $headers
     */
    private function render(Throwable $e, int $status, array $headers = []): ResponseInterface
    {
        if ($status >= 500) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        $response = $this->responder->text($this->format($e, $status), $status);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function format(Throwable $e, int $status): string
    {
        if ($this->debug) {
            return sprintf(
                "%s: %s\nin %s:%d\n\n%s",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
        }

        // An HttpException message is developer-authored and intentional (e.g.
        // abort(403, 'not yours') or a validation error), so it's safe to show.
        // Any other Throwable is an unexpected fault: never leak its message —
        // fall back to the status' reason phrase.
        if ($e instanceof HttpException && $e->getMessage() !== '') {
            return $e->getMessage();
        }

        return Status::reasonFor($status) ?? 'Error';
    }
}
