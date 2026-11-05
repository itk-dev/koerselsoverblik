<?php

namespace App;

use App\Import\DependencyInjection\ValidateMappingsPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Sets UTC as the PHP default time zone for the web and the console. The application stores
     * and calculates all dates and times in UTC. See ADR 005.
     */
    public function boot(): void
    {
        date_default_timezone_set('UTC');

        parent::boot();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ValidateMappingsPass());
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
