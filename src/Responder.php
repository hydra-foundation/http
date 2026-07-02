<?php

declare(strict_types=1);

namespace Hydra\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds common PSR-7 responses from the PSR-17 factories.
 *
 * Usable on its own (inject it) or behind the base Controller. Depends only on
 * PSR-17 interfaces, so the concrete implementation stays an application choice.
 */
final class Responder
{
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
    ) {}

    public function text(string $body, int|Status $status = Status::Ok): ResponseInterface
    {
        return $this->make($body, $status, 'text/plain; charset=utf-8');
    }

    public function html(string $body, int|Status $status = Status::Ok): ResponseInterface
    {
        return $this->make($body, $status, 'text/html; charset=utf-8');
    }

    public function json(mixed $data, int|Status $status = Status::Ok): ResponseInterface
    {
        $body = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->make($body, $status, 'application/json');
    }

    public function noContent(int|Status $status = Status::NoContent): ResponseInterface
    {
        return $this->responses->createResponse(Status::toInt($status));
    }

    public function redirect(string $location, int|Status $status = Status::Found): ResponseInterface
    {
        return $this->responses->createResponse(Status::toInt($status))->withHeader('Location', $location);
    }

    private function make(string $body, int|Status $status, string $contentType): ResponseInterface
    {
        return $this->responses->createResponse(Status::toInt($status))
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($body));
    }
}
