<?php

declare(strict_types=1);

namespace Hydra\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Query
 *
 * Typed reader for a request's query string
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
