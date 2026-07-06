<?php

declare(strict_types=1);

namespace Hydra\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Forces every request onto HTTPS when the app opts in.
 *
 * Two jobs, both gated on the $enabled flag (the app passes its FORCE_HTTPS
 * config value when binding this) so local http dev is untouched by default:
 *
 *   - an insecure request is answered with a 301 to the same URL on https,
 *     before any session or routing work — no point starting a session on a
 *     request we're about to redirect;
 *   - a secure request proceeds, and its response carries an HSTS header so the
 *     browser upgrades subsequent requests itself, without the round trip.
 *
 * "Secure" honours X-Forwarded-Proto only when the app opts in via
 * $trustForwardedProto. Behind a TLS-terminating proxy (Traefik in our
 * dev/prod stacks) the request arrives here as plain http with the original
 * scheme in that header, so trusting it is what makes the check work — but
 * the header is client-supplied like any other: an attacker hitting the app
 * directly can send "X-Forwarded-Proto: https" and skip the redirect (and
 * collect an HSTS header over plain http). Trust must therefore be the app's
 * explicit declaration that a proxy it controls sets the header, never an
 * implicit default.
 *
 * It sits near the OUTERMOST of the stack (just inside the header/logging
 * decorators, outside the error handler): the upgrade should happen before the
 * app does any real work, and it must see the request before the router.
 *
 * The flags are plain bools rather than an app config object so the
 * middleware stays app-agnostic; the app binds them with its own config values.
 */
final class ForceHttpsMiddleware implements MiddlewareInterface
{
    /** One year, and apply to subdomains — the conventional HSTS baseline. */
    private const HSTS = 'max-age=31536000; includeSubDomains';

    public function __construct(
        private readonly bool $enabled,
        private readonly Responder $respond,
        private readonly bool $trustForwardedProto = false,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        if (!$this->isSecure($request)) {
            // Drop any explicit port: an http URL may carry :80, which is wrong
            // for https — clearing it lets the default 443 apply.
            $secureUrl = $request->getUri()->withScheme('https')->withPort(null);

            return $this->respond->redirect((string) $secureUrl, Status::MovedPermanently);
        }

        return $handler->handle($request)
            ->withHeader('Strict-Transport-Security', self::HSTS);
    }

    private function isSecure(ServerRequestInterface $request): bool
    {
        if ($request->getUri()->getScheme() === 'https') {
            return true;
        }

        // The forwarded scheme is only meaningful when the app has declared
        // that a proxy it controls sets it (TLS terminated upstream). With no
        // proxy, the header is attacker-controlled — consulting it here would
        // let any direct client spoof its way past the redirect.
        return $this->trustForwardedProto
            && strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https';
    }
}
