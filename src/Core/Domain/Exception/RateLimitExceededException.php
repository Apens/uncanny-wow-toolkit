<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Exception;

class RateLimitExceededException extends \RuntimeException implements UncannyWoWException
{
    public function __construct(
        private readonly ?int $retryAfterSeconds = null,
        string $message = 'API rate limit exceeded.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
