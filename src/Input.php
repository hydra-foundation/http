<?php

declare(strict_types=1);

namespace Hydra\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Typed reader for a request's parsed body (form fields).
 *
 * The sibling of {@see Query} (and of {@see Htmx}): built from the request in
 * a controller — `Input::fromRequest($request)` — to collapse PSR-7's untyped
 * body surface (`getParsedBody()` returns array|object|null) into a few typed,
 * defensive accessors, instead of casting by hand at every call site. The
 * accessors themselves live on {@see FieldReader}, shared with `Query`.
 *
 * The parsed body this reads is only populated out of the box for POST forms
 * (PHP's SAPI behavior). JSON bodies and urlencoded PUT/PATCH need
 * {@see ParseBodyMiddleware} in the stack; multipart on non-POST methods is
 * not parsed at all.
 */
final class Input extends FieldReader
{
    public function __construct(ServerRequestInterface $request)
    {
        parent::__construct((array) $request->getParsedBody());
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        return new self($request);
    }
}
