<?php declare(strict_types=1);

namespace JournyIO\SDK;

use InvalidArgumentException;

final class UserIdentified
{
    private $userId;
    private $email;

    public function __construct(string $userId = null, string $email = null)
    {
        if (empty($userId) && empty($email)) {
            throw new InvalidArgumentException("User ID or email needs to set or both");
        }

        $this->userId = $userId;
        $this->email = $email;
    }

    public static function byUserId(string $userId): UserIdentified
    {
        return new UserIdentified($userId, null);
    }

    public static function byEmail(string $email): UserIdentified
    {
        return new UserIdentified(null, $email);
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getEmail()
    {
        return $this->email;
    }
}
