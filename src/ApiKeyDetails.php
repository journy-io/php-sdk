<?php declare(strict_types=1);

namespace JournyIO\SDK;

final class ApiKeyDetails
{
    private $permissions;

    /**
     * @param string[] $permissions
     */
    public function __construct(array $permissions)
    {
        $this->permissions = $permissions;
    }

    public function getPermissions()
    {
        return $this->permissions;
    }
}
