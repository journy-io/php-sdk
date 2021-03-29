<?php declare(strict_types=1);

namespace JournyIO\SDK;

use DateTimeInterface;

final class Event
{
    private $name;
    private $user;
    private $account;
    private $recordedAt;
    private $metadata;

    private function __construct(
        string $name,
        UserIdentified $user = null,
        AccountIdentified $account = null,
        DateTimeInterface $recordedAt = null,
        array $metadata = []
    ) {
        $this->name = $name;
        $this->user = $user;
        $this->account = $account;
        $this->recordedAt = $recordedAt ? clone $recordedAt : null;
        $this->metadata = $metadata;
    }

    public function happenedAt(DateTimeInterface $time): Event
    {
        return new Event(
            $this->name,
            $this->user,
            $this->account,
            $time,
            $this->metadata
        );
    }

    public function withMetadata(array $metadata): Event
    {
        return new Event(
            $this->name,
            $this->user,
            $this->account,
            $this->recordedAt,
            array_merge(
                $this->metadata,
                $metadata
            )
        );
    }

    public static function forUser(string $name, UserIdentified $identifiers): Event
    {
        return new Event($name, $identifiers);
    }

    public static function forAccount(string $name, AccountIdentified $identifiers): Event
    {
        return new Event($name, null, $identifiers);
    }

    public static function forUserInAccount(string $name, UserIdentified $user, AccountIdentified $account): Event
    {
        return new Event($name, $user, $account);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getAccount()
    {
        return $this->account;
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
