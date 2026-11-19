<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the roles from the role claim of the identity provider.
 *
 * The role map (OIDC_ROLE_MAP) changes an app role value of the identity provider to a role, for
 * example "admin" to ROLE_ADMIN. A value that is not in the map stays as it is.
 *
 * It keeps ROLE_ADMIN and the permission roles, and ignores all other values. It logs the ignored
 * values as a warning. The permission roles are the roles that ROLE_ADMIN gives in the role
 * hierarchy in config/packages/security.yaml.
 *
 * Put each new permission role under ROLE_ADMIN. A role under another parent is not a permission
 * role: this class ignores it at login, and RouteGuardTest does not accept it on a route.
 */
final readonly class OidcRoleMapper
{
    /**
     * @var list<string>
     */
    private array $knownRoles;

    /**
     * @param array<string, list<string>> $roleHierarchy
     * @param array<string, string>       $roleMap
     */
    public function __construct(
        #[Autowire(param: 'security.role_hierarchy.roles')]
        array $roleHierarchy,
        #[Autowire(env: 'OIDC_ROLE_CLAIM')]
        private string $roleClaim,
        #[Autowire(env: 'json:OIDC_ROLE_MAP')]
        private array $roleMap = [],
        #[Autowire(service: 'monolog.logger.openid_connect_audit')]
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->knownRoles = ['ROLE_ADMIN', ...$roleHierarchy['ROLE_ADMIN'] ?? []];
    }

    /**
     * @param array<string, mixed> $claims
     *
     * @return list<string>
     */
    public function map(array $claims): array
    {
        $values = $claims[$this->roleClaim] ?? [];
        if (is_string($values)) {
            $values = [$values];
        }
        if (!is_array($values)) {
            return [];
        }

        $roles = [];
        $ignored = [];
        foreach (array_filter($values, is_string(...)) as $value) {
            $role = $this->roleMap[$value] ?? $value;
            if (in_array($role, $this->knownRoles, true)) {
                $roles[] = $role;
            } else {
                $ignored[] = $value;
            }
        }

        $ignored = array_values(array_unique($ignored));
        if ([] !== $ignored) {
            // The values come from the identity provider, not from the driving data.
            $this->logger->warning(sprintf('ignored role values [%s]', implode(', ', $ignored)), ['values' => $ignored]);
        }

        return array_values(array_unique($roles));
    }
}
