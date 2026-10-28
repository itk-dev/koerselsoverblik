<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Makes sure that every application route requires a permission role.
 *
 * The permission roles are the roles that ROLE_ADMIN gives in the role hierarchy. Thus
 * config/packages/security.yaml is the only list of permission roles.
 */
final class RouteGuardTest extends KernelTestCase
{
    /**
     * The application routes that anonymous users can get. The bundle's OIDC login route is not
     * an application route.
     */
    private const array PUBLIC_ROUTES = ['openid_connect_generic'];

    public function testEveryApplicationRouteRequiresAPermissionRole(): void
    {
        $container = static::getContainer();
        $router = $container->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);
        $permissionRoles = $this->permissionRoles($container->getParameter('security.role_hierarchy.roles'));

        $checked = 0;
        $violations = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (!is_string($controller) || !str_starts_with($controller, 'App\\')) {
                continue;
            }
            if (in_array($name, self::PUBLIC_ROUTES, true)) {
                continue;
            }

            ++$checked;
            foreach ($this->violations($controller, $permissionRoles) as $violation) {
                $violations[] = sprintf('%s (%s): %s', $name, $controller, $violation);
            }
        }

        self::assertGreaterThan(0, $checked, 'The test found no application routes.');
        self::assertSame([], $violations);
    }

    /**
     * @return list<string>
     */
    private function permissionRoles(mixed $hierarchy): array
    {
        self::assertIsArray($hierarchy);
        self::assertArrayHasKey('ROLE_ADMIN', $hierarchy);
        $roles = $hierarchy['ROLE_ADMIN'];
        self::assertIsArray($roles);
        self::assertNotEmpty($roles);

        return array_values(array_filter($roles, is_string(...)));
    }

    /**
     * @param list<string> $permissionRoles
     *
     * @return list<string>
     */
    private function violations(string $controller, array $permissionRoles): array
    {
        [$class, $method] = str_contains($controller, '::')
            ? explode('::', $controller, 2)
            : [$controller, '__invoke'];
        self::assertTrue(class_exists($class), sprintf('The class %s does not exist.', $class));

        $reflectionClass = new \ReflectionClass($class);
        $attributes = [
            ...$reflectionClass->getAttributes(IsGranted::class),
            ...$reflectionClass->getMethod($method)->getAttributes(IsGranted::class),
        ];
        if ([] === $attributes) {
            return ['no IsGranted'];
        }

        $violations = [];
        foreach ($attributes as $attribute) {
            $role = $attribute->newInstance()->attribute;
            if (!is_string($role) || !in_array($role, $permissionRoles, true)) {
                $violations[] = sprintf('IsGranted must use a permission role, not %s', is_string($role) ? $role : 'an expression');
            }
        }

        return $violations;
    }
}
