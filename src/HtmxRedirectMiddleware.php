<?php

declare(strict_types=1);

namespace Hydra\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Rewrites a redirect into the form htmx acts on.
 *
 * htmx issues an XHR, so the browser follows a 3xx itself and htmx swaps the
 * redirect target's body into the page instead of navigating. A 204 carrying
 * HX-Redirect is what makes it navigate. Normalising here rather than at each
 * call site means a handler returns a plain redirect and cannot forget.
 */
final class HtmxRedirectMiddleware implements MiddlewareInterface
{
    /** Redirects the client follows via Location. 304 carries none. */
    private const REDIRECTS = [301, 302, 303, 307, 308];

    public function __construct(private readonly Responder $respond) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (
            !Htmx::fromRequest($request)->isHtmx()
            || !in_array($response->getStatusCode(), self::REDIRECTS, true)
            || !$response->hasHeader('Location')
        ) {
            return $response;
        }

        return (new HtmxResponse)
            ->redirect($response->getHeaderLine('Location'))
            ->applyTo($this->respond->noContent());
    }
}
