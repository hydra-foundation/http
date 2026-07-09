<?php

declare(strict_types=1);

namespace Hydra\Http;

use Hydra\Http\Exceptions\HttpException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Everything an {@see Contracts\ErrorRendererInterface} needs to turn a caught
 * throwable into a response, in one value object: the error, the request that
 * triggered it, the resolved HTTP status, and whether debug detail is allowed.
 *
 * Passing a VO rather than a positional argument list keeps the renderer
 * signature stable as this grows, and — more importantly — gives the
 * production-safety leak-guard a single home in {@see clientMessage()}. A
 * renderer is free to build a rich debug page from {@see $error} and
 * {@see $debug}, but for the message shown to a client it should lean on
 * clientMessage() so no renderer can accidentally leak an internal exception
 * message into a production response.
 */
final readonly class ErrorContext
{
    public function __construct(
        public Throwable $error,
        public ServerRequestInterface $request,
        public int $status,
        public bool $debug,
    ) {}

    /**
     * The message safe to show a client, regardless of debug mode.
     *
     * An {@see HttpException} message is developer-authored and intentional
     * (e.g. abort(403, 'not yours'), a validation summary), so it is shown. Any
     * other throwable is an unexpected fault whose message may contain internals
     * (a DSN, a file path, a stack detail) and is never shown — it falls back to
     * the status' reason phrase.
     */
    public function clientMessage(): string
    {
        if ($this->error instanceof HttpException && $this->error->getMessage() !== '') {
            return $this->error->getMessage();
        }

        return Status::reasonFor($this->status) ?? 'Error';
    }
}
