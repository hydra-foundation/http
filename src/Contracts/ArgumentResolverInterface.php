<?php

declare(strict_types=1);

namespace Hydra\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the positional arguments to invoke a route target with, by
 * inspecting the target's signature.
 *
 * This is the seam that lets controllers declare what they need rather than
 * accept a fixed (ServerRequestInterface): a parameter type-hinted for the
 * request gets the request; a parameter named after a {placeholder} gets that
 * route value coerced to its declared scalar type. It does not invoke the
 * target or build a response — it only produces the argument list.
 */
interface ArgumentResolverInterface
{
    /**
     * @param array<string, string> $routeParams matched placeholders, url-decoded
     *
     * @return list<mixed> positional arguments for invoking $target
     */
    public function resolve(callable $target, ServerRequestInterface $request, array $routeParams): array;
}
