<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Creates or updates the user from the claims of a validated OIDC login.
 *
 * The user is identified by the upn claim. The roles from the role claim replace the stored
 * roles, and the login is written to the authentication log.
 */
final readonly class OidcLogin
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private OidcRoleMapper $roleMapper,
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
            throw new CustomUserMessageAuthenticationException('The upn claim is missing.');
        }

        $user = $this->userRepository->findOneByEmail($upn);
        if (null === $user) {
            $user = new User($upn);
            $this->entityManager->persist($user);
        }

        $roles = $this->roleMapper->map($claims);
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
