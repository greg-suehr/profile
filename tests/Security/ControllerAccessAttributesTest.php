<?php

declare(strict_types=1);

namespace App\Tests\Security;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ControllerAccessAttributesTest extends TestCase
{
    private const SRC = __DIR__.'/../../src';

    /** Directories whose every concrete controller requires a logged-in user. */
    private const KATZEN_DIRS = ['Katzen/Controller', 'Katzen/Api'];

    /**
     * @dataProvider adminControllers
     */
    public function testEasyAdminControllersRequireAdmin(string $fqcn): void
    {
        self::assertSame(
            ['ROLE_ADMIN'],
            $this->grantedRoles($fqcn),
            sprintf('%s must carry #[IsGranted(\'ROLE_ADMIN\')] at class level.', $fqcn),
        );
    }

    /**
     * @dataProvider dashboardControllers
     */
    public function testDashboardsDeclareAllowedControllers(string $fqcn): void
    {
        $attrs = (new \ReflectionClass($fqcn))->getAttributes(AdminDashboard::class);
        self::assertCount(1, $attrs, sprintf('%s must use #[AdminDashboard].', $fqcn));

        $dashboard = $attrs[0]->newInstance();
        self::assertNotEmpty(
            $dashboard->allowedControllers,
            sprintf('%s must list its own CRUD controllers in allowedControllers.', $fqcn),
        );
        self::assertArrayHasKey(
            'condition',
            $dashboard->routeOptions,
            sprintf('%s must restrict its route to its own host.', $fqcn),
        );
    }

    /**
     * @dataProvider katzenControllers
     */
    public function testKatzenControllersRequireLogin(string $fqcn): void
    {
        $roles = $this->grantedRoles($fqcn);

        self::assertNotEmpty(
            $roles,
            sprintf('%s must carry #[IsGranted(\'ROLE_USER\')] (or stronger) at class level.', $fqcn),
        );
        self::assertNotContains('PUBLIC_ACCESS', $roles);
    }

    public static function adminControllers(): iterable
    {
        foreach (self::controllerClasses(['']) as $fqcn) {
            if (is_subclass_of($fqcn, AbstractCrudController::class) || is_subclass_of($fqcn, AbstractDashboardController::class)) {
                yield $fqcn => [$fqcn];
            }
        }
    }

    public static function dashboardControllers(): iterable
    {
        foreach (self::controllerClasses(['']) as $fqcn) {
            if (is_subclass_of($fqcn, AbstractDashboardController::class)) {
                yield $fqcn => [$fqcn];
            }
        }
    }

    public static function katzenControllers(): iterable
    {
        foreach (self::controllerClasses(self::KATZEN_DIRS) as $fqcn) {
            if (is_subclass_of($fqcn, AbstractCrudController::class) || is_subclass_of($fqcn, AbstractDashboardController::class)) {
                continue; // covered by the ROLE_ADMIN test
            }
            yield $fqcn => [$fqcn];
        }
    }

    /** @return list<string> roles from class-level #[IsGranted] attributes */
    private function grantedRoles(string $fqcn): array
    {
        $roles = [];
        foreach ((new \ReflectionClass($fqcn))->getAttributes(IsGranted::class) as $attr) {
            $attribute = $attr->getArguments()[0] ?? $attr->getArguments()['attribute'] ?? null;
            if (\is_string($attribute)) {
                $roles[] = $attribute;
            }
        }

        return $roles;
    }

    /**
     * Concrete classes found under src/<dir>/**, restricted to *Controller*
     * paths. An empty-string dir means "all of src/".
     *
     * @param list<string> $dirs
     * @return list<class-string>
     */
    private static function controllerClasses(array $dirs): array
    {
        $src = realpath(self::SRC);
        $classes = [];

        foreach ($dirs as $dir) {
            $root = rtrim($src.'/'.$dir, '/');
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                $path = $file->getPathname();
                if ('php' !== $file->getExtension()) {
                    continue;
                }
                if ('' === $dir && !str_contains($path, '/Controller/')) {
                    continue;
                }

                $relative = substr($path, \strlen($src) + 1, -4);
                $fqcn = 'App\\'.str_replace('/', '\\', $relative);

                if (!class_exists($fqcn)) {
                    continue;
                }
                $ref = new \ReflectionClass($fqcn);
                if ($ref->isAbstract() || $ref->isInterface() || $ref->isTrait()) {
                    continue;
                }
                $classes[] = $fqcn;
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }
}
