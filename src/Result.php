<?php declare(strict_types=1);

namespace JournyIO\SDK;

final class Result
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
        $result = null
    ) {
        $this->succeeded = $succeeded;
        $this->rateLimited = $rateLimited;
        $this->remainingRequests = $remainingRequests;
        $this->maxRequests = $maxRequests;
        $this->errors = $errors;
        $this->result = $result;
    }

    public function succeeded()
    {
        return $this->succeeded;
    }

    public function rateLimited()
    {
        return $this->rateLimited;
    }

    public function remainingRequests()
    {
        return $this->remainingRequests;
    }

    public function maxRequests()
    {
        return $this->maxRequests;
    }

    public function errors()
    {
        return $this->errors;
    }

    public function result()
    {
        return $this->result;
    }
}
