<?php

declare(strict_types=1);

namespace JournyIO\SDK;

final class PhoneNumber
{
    private $number;

    public function __construct(string $number)
    {
        $this->number = $number;
    }

    public function __toString(): string
    {
        return $this->number;
    }
}
