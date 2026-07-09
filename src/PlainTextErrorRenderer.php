<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Contracts\ErrorRendererInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The default error renderer: plain text, matching what the framework has always
 * emitted. In debug mode it shows the class, message, origin, and stack trace;
 * otherwise it shows only the client-safe message ({@see ErrorContext::clientMessage()}).
 *
 * This is the renderer the kernel binds out of the box, so an app that wires
 * nothing gets exactly the previous behaviour. An app wanting HTML/htmx/JSON
 * binds its own {@see ErrorRendererInterface} instead.
 */
final class PlainTextErrorRenderer implements ErrorRendererInterface
{
    public function __construct(private readonly Responder $responder) {}

    public function render(ErrorContext $context): ResponseInterface
    {
        return $this->responder->text($this->body($context), $context->status);
    }

    private function body(ErrorContext $context): string
    {
        if ($context->debug) {
            $error = $context->error;

            return sprintf(
                "%s: %s\nin %s:%d\n\n%s",
                $error::class,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine(),
                $error->getTraceAsString(),
            );
        }

        return $context->clientMessage();
    }
}
