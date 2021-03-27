<?php declare(strict_types=1);

namespace JournyIO\SDK;

use DateTimeInterface;
use InvalidArgumentException;

final class Event
{
    private $name;
    private $userId;
    private $email;
    private $accountId;
    private $recordedAt;
    private $metadata;

    private function __construct(
        string $name,
        string $userId = null,
        string $accountId = null,
        string $email = null,
        DateTimeInterface $recordedAt = null,
        array $metadata = []
    ) {
        $this->name = $name;
        $this->userId = $userId;
        $this->accountId = $accountId;
        $this->email = $email;
        $this->recordedAt = $recordedAt ? clone $recordedAt : null;
        $this->metadata = $metadata;
    }

    public function happenedAt(DateTimeInterface $time): Event
    {
        return new Event(
            $this->name,
            $this->userId,
            $this->accountId,
            $this->email,
            $time,
            $this->metadata
        );
    }

    public function withMetadata(array $metadata): Event
    {
        return new Event(
            $this->name,
            $this->userId,
            $this->accountId,
            $this->email,
            $this->recordedAt,
            array_merge(
                $this->metadata,
                $metadata
            )
        );
    }

    public static function forUser(string $name, string $userId): Event
    {
        if (empty($userId)) {
            throw new InvalidArgumentException("User ID cannot be empty!");
        }

        return new Event($name, $userId);
    }

    public static function forEmail(string $name, string $email): Event
    {
        if (empty($email)) {
            throw new InvalidArgumentException("User ID cannot be empty!");
        }

        return new Event($name, null, null, $email);
    }

    public static function forAccount(string $name, string $accountId): Event
    {
        if (empty($accountId)) {
            throw new InvalidArgumentException("Account ID cannot be empty!");
        }

        return new Event($name, null, $accountId);
    }

    public static function forUserInAccount(string $name, string $userId, string $accountId): Event
    {
        if (empty($userId)) {
            throw new InvalidArgumentException("User ID cannot be empty!");
        }

        if (empty($accountId)) {
            throw new InvalidArgumentException("Account ID cannot be empty!");
        }

        return new Event($name, $userId, $accountId);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getRecordedAt()
    {
        return $this->recordedAt;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }
}
