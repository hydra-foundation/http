<?php

declare(strict_types=1);

namespace Hydra\Http\Contracts;

use Hydra\Http\ErrorContext;
use Psr\Http\Message\ResponseInterface;

/**
 * Turns a caught error into a response body and content type.
 *
 * This is the pluggable half of error handling: {@see \Hydra\Http\ErrorHandlerMiddleware}
 * owns the invariant parts (detecting faults, logging 5xx, applying an
 * HttpException's mapped headers) and delegates only the presentation here, so
 * an app can render errors as HTML, an htmx fragment, or JSON instead of the
 * default plain text — by binding its own renderer at the composition root.
 *
 * A renderer is pure presentation: it must not log, decide which errors are
 * faults, or apply mapped headers — the middleware does all of that around it.
 * It receives the status already resolved and a debug flag, and should use
 * {@see ErrorContext::clientMessage()} for anything shown to a client so it
 * cannot leak an internal message in production.
 *
 * Content negotiation (inspecting Accept, preferring htmx) is deliberately NOT
 * defined here — it is app policy an app's renderer implements explicitly, not
 * something the framework auto-detects.
 */
interface ErrorRendererInterface
{
    public function render(ErrorContext $context): ResponseInterface;
}
