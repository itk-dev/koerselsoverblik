<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use ItkDev\OpenIdConnect\Exception\ClaimsException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates or updates the user from the claims of a validated OIDC login.
 *
 * The user is identified by the upn claim. The roles from the role claim replace the stored
 * roles, and the login is written to the authentication log. The log shows the roles, so it also
 * shows a value of the role claim that gives no permission role.
 */
final readonly class OidcLogin
{
    /**
     * The app role values of Entra ID that have another Symfony role name. The app role
     * "Slutbruger" has the value "bruger". It is the Symfony convention ROLE_USER.
     */
    private const array ROLE_NAMES = ['BRUGER' => 'USER'];

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'OIDC_ROLE_CLAIM')]
        private string $roleClaim,
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.openid_connect_audit')]
        private LoggerInterface $auditLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function login(array $claims): User
    {
        $upn = $claims['upn'] ?? null;
        if (!is_string($upn) || '' === trim($upn)) {
            // OidcAuthenticator wraps it in an authentication exception.
            throw new ClaimsException('The upn claim is missing.');
        }

        $user = $this->userRepository->findOneByEmail($upn);
        if (null === $user) {
            $user = new User($upn);
            $this->entityManager->persist($user);
        }

        // Each app role of Entra ID becomes a role, for example "admin" becomes ROLE_ADMIN. The role
        // hierarchy in config/packages/security.yaml gives the permission roles.
        $values = array_filter((array) ($claims[$this->roleClaim] ?? []), is_string(...));
        $roles = array_values(array_unique(array_map(static function (string $value): string {
            $role = strtoupper($value);

            return 'ROLE_'.(self::ROLE_NAMES[$role] ?? $role);
        }, $values)));
        $now = $this->clock->now();
        $user->setRoles($roles);
        $user->setLastLoginAt($now);
        $this->entityManager->flush();

        $this->auditLogger->info(
            sprintf('user %s logged in with roles [%s] at %s', $user->getEmail(), implode(', ', $roles), $now->format(\DATE_ATOM)),
            ['email' => $user->getEmail(), 'roles' => $roles],
        );

        return $user;
    }
}
