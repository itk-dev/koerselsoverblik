<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the roles from the role claim of the identity provider.
 *
 * It keeps ROLE_ADMIN and the permission roles, and ignores all other values. The permission
 * roles are the roles that ROLE_ADMIN gives in the role hierarchy in
 * config/packages/security.yaml.
 */
final readonly class OidcRoleMapper
{
    /**
     * @var list<string>
     */
    private array $knownRoles;

    /**
     * @param array<string, list<string>> $roleHierarchy
     */
    public function __construct(
        #[Autowire('%security.role_hierarchy.roles%')]
        array $roleHierarchy,
        #[Autowire(env: 'OIDC_ROLE_CLAIM')]
        private string $roleClaim,
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

        return array_values(array_unique(array_filter(
            $values,
            fn (mixed $value): bool => is_string($value) && in_array($value, $this->knownRoles, true),
        )));
    }
}
