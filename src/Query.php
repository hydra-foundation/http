<?php

declare(strict_types=1);

namespace Hydra\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Typed reader for a request's query string.
 *
 * The sibling of {@see Input}: built from the request in a controller —
 * `Query::fromRequest($request)` — it reads `getQueryParams()` with the same
 * typed, defensive accessors (shared via {@see FieldReader}), so `?page=2`
 * is `$query->int('page', 1)` instead of a hand-rolled cast.
 *
 * Query params are whatever the PSR-7 implementation parsed from the URL
 * (Nyholm's provider uses `$_GET`), so values are strings or arrays — the
 * int/float/bool accessors exist precisely to type those strings.
 */
final class Query extends FieldReader
{
    public function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request->getQueryParams());
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        return new self($request);
    }
}
