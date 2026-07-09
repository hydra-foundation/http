<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Exceptions\BadRequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Populates getParsedBody() for the request bodies PHP's SAPI doesn't: JSON
 * (any method) and urlencoded forms on PUT/PATCH/DELETE. Without this, PSR-7
 * implementations only carry a parsed body for POST forms — a JSON POST or a
 * urlencoded PUT silently reads as empty through {@see Input}, which is a
 * correctness footgun, not a feature.
 *
 * Explicit mechanism, no magic: an app opts in by listing this class in its
 * middleware stack. Behavior:
 *
 *  - Never clobbers: a request that already has a parsed body (POST forms,
 *    POST multipart — populated upstream from PHP's globals) passes through
 *    untouched. GET/HEAD and empty bodies also pass through.
 *  - `application/json` and any `+json` suffix type: the decoded array becomes
 *    the parsed body. A JSON scalar or null is valid JSON but not a body map,
 *    so the parsed body stays null. Malformed JSON on a non-empty body throws
 *    {@see BadRequestException} (400) — a client asserting JSON but sending
 *    garbage is a client error; reading it as empty would hide bugs.
 *  - `application/x-www-form-urlencoded`: parsed with parse_str(). This is
 *    what makes form submissions over PUT/PATCH work. Note parse_str() is not
 *    capped by max_input_vars the way PHP's own POST parsing is; the request
 *    body size limit is what bounds it.
 *  - `multipart/form-data` on non-POST methods is NOT handled (PHP only
 *    parses multipart for POST; hand-parsing it is out of scope).
 */
final class ParseBodyMiddleware implements MiddlewareInterface
{
    private const IGNORED_METHODS = ['GET', 'HEAD'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getParsedBody() !== null
            || in_array($request->getMethod(), self::IGNORED_METHODS, true)) {
            return $handler->handle($request);
        }

        $body = $request->getBody();
        $raw = (string) $body;
        // Leave a re-readable stream for anything downstream that reads raw.
        // PSR-7 permits non-seekable streams, whose rewind() throws — for
        // those, downstream raw readers were never possible anyway.
        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (trim($raw) === '') {
            return $handler->handle($request);
        }

        // The media type only — parameters like "; charset=utf-8" don't matter.
        $type = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));

        if ($type === 'application/json' || str_ends_with($type, '+json')) {
            $decoded = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new BadRequestException('Malformed JSON request body.');
            }

            if (is_array($decoded)) {
                $request = $request->withParsedBody($decoded);
            }
        } elseif ($type === 'application/x-www-form-urlencoded') {
            parse_str($raw, $data);
            $request = $request->withParsedBody($data);
        }

        return $handler->handle($request);
    }
}
