<?php declare(strict_types=1);

namespace JournyIO\SDK;

final class CallResult
{
    private $succeeded;
    private $rateLimited;
    private $remainingRequests;
    private $maxRequests;
    private $errors;
    private $result;

    public function __construct(
        bool $succeeded,
        bool $rateLimited,
        int $remainingRequests,
        int $maxRequests,
        array $errors = [],
        ReturnValue $result = null
    ) {
        $this->succeeded = $succeeded;
        $this->rateLimited = $rateLimited;
        $this->remainingRequests = $remainingRequests;
        $this->maxRequests = $maxRequests;
        $this->errors = $errors;
        $this->result = $result;
    }

    public function succeeded(): bool
    {
        return $this->succeeded;
    }

    public function rateLimited(): bool
    {
        return $this->rateLimited;
    }

    public function remainingRequests(): int
    {
        return $this->remainingRequests;
    }

    public function maxRequests(): int
    {
        return $this->maxRequests;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function result(): ReturnValue
    {
        return $this->result;
    }
}
