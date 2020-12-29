<?php declare(strict_types=1);

namespace JournyIO\SDK;

use DateTimeInterface;
use InvalidArgumentException;

final class AppEvent
{
    private $name;
    private $userId;
    private $accountId;
    private $recordedAt;

    private function __construct(
        string $name,
        string $userId = null,
        string $accountId = null,
        DateTimeInterface $recordedAt = null
    ) {
        $this->name = $name;
        $this->userId = $userId;
        $this->accountId = $accountId;
        $this->recordedAt = $recordedAt ? clone $recordedAt : null;
    }

    public function happenedAt(DateTimeInterface $time): AppEvent
    {
        return new AppEvent(
            $this->name,
            $this->userId,
            $this->accountId,
            $time
        );
    }

    public static function forUser(string $name, string $userId): AppEvent
    {
        if (empty($userId)) {
            throw new InvalidArgumentException("User ID cannot be empty!");
        }

        return new AppEvent($name, $userId);
    }

    public static function forAccount(string $name, string $accountId): AppEvent
    {
        if (empty($accountId)) {
            throw new InvalidArgumentException("Account ID cannot be empty!");
        }

        return new AppEvent($name, null, $accountId);
    }

    public static function forUserInAccount(string $name, string $userId, string $accountId): AppEvent
    {
        if (empty($userId)) {
            throw new InvalidArgumentException("User ID cannot be empty!");
        }

        if (empty($accountId)) {
            throw new InvalidArgumentException("Account ID cannot be empty!");
        }

        return new AppEvent($name, $userId, $accountId);
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

    public function getRecordedAt()
    {
        return $this->recordedAt;
    }
}
