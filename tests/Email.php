<?php

declare(strict_types=1);

namespace JournyIO\SDK;

final class Email
{
    private $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
