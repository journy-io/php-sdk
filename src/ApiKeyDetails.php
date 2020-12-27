<?php declare(strict_types=1);

namespace JournyIO\SDK;

final class ApiKeyDetails implements ReturnValue
{
    private $permissions;

    /**
     * @param string[] $permissions
     */
    public function __construct(array $permissions)
    {
        $this->permissions = $permissions;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
