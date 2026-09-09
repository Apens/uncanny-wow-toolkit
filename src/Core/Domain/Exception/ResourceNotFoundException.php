<?php

declare(strict_types=1);

namespace UncannyWoW\Core\Domain\Exception;

class ResourceNotFoundException extends \RuntimeException implements UncannyWoWException
{
    public function __construct(
        private readonly string $resourceType,
        private readonly string $identifier,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $effectiveMessage = $message !== '' ? $message : sprintf('Resource of type "%s" with identifier "%s" was not found.', $resourceType, $identifier);
        parent::__construct($effectiveMessage, $code, $previous);
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
