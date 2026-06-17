<?php

declare(strict_types=1);

namespace Hydra\Http\Contracts;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    /**
     * Send a PSR-7 response to the client (status line, headers, body).
     */
    public function emit(ResponseInterface $response): void;
}
