<?php

declare(strict_types=1);

namespace QrCommunication\VivaCloudTerminal\Exceptions;

class VivaException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?array $responseBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getErrorCode(): ?int
    {
        return $this->responseBody['ErrorCode'] ?? $this->responseBody['errorCode'] ?? null;
    }

    public function getErrorText(): ?string
    {
        return $this->responseBody['ErrorText']
            ?? $this->responseBody['message']
            ?? $this->responseBody['detail']
            ?? null;
    }
}
