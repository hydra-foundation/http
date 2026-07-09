<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Contracts\ErrorRendererInterface;
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
 * This class owns the invariant parts of error handling — deciding the status,
 * logging faults, applying an HttpException's mapped headers — and delegates the
 * presentation to a pluggable {@see ErrorRendererInterface}. The default
 * renderer (bound by the kernel) emits plain text; an app binds its own to get
 * HTML/htmx/JSON. If the renderer itself throws, this middleware does NOT catch
 * it: it bubbles to {@see HttpKernel}'s last-resort boundary, which emits a bare
 * dependency-free 500 — exactly the case that boundary exists for.
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
        private readonly ErrorRendererInterface $renderer,
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
            return $this->render($e, $request, $e->status(), $e->headers());
        } catch (Throwable $e) {
            return $this->render($e, $request, 500);
        }
    }

    /**
     * Log the error if it is a fault (5xx), delegate rendering, then apply any
     * headers the error mapped (e.g. Allow on a 405).
     *
     * @param array<string, string> $headers
     */
    private function render(Throwable $e, ServerRequestInterface $request, int $status, array $headers = []): ResponseInterface
    {
        if ($status >= 500) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        $response = $this->renderer->render(new ErrorContext($e, $request, $status, $this->debug));

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
