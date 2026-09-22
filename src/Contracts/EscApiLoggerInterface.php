<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

interface EscApiLoggerInterface
{
    public function log(
        RequestInterface $request,
        ?ResponseInterface $response,
        ?Throwable $error,
        float $durationMilliseconds,
    ): void;
}
