<?php declare(strict_types=1);

namespace JournyIO\SDK;

use InvalidArgumentException;

final class AccountIdentified
{
    private $accountId;
    private $domain;

    public function __construct(string $accountId = null, string $domain = null)
    {
        if (empty($accountId) && empty($domain)) {
            throw new InvalidArgumentException("Account ID or domain needs to set or both");
        }

        $this->accountId = $accountId;
        $this->domain = $domain;
    }

    public static function byAccountId(string $accountId)
    {
        return new AccountIdentified($accountId, null);
    }

    public static function byDomain(string $domain)
    {
        return new AccountIdentified(null, $domain);
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function getDomain()
    {
        return $this->domain;
    }
}
