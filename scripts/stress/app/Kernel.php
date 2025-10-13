<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\StressApp;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        // Load bundle services first
        $bundleServicesPath = dirname(__DIR__, 3) . '/config/services.php';
        if (file_exists($bundleServicesPath)) {
            $loader->load($bundleServicesPath);
        }

        // Then load our custom services
        $loader->load($this->getProjectDir() . '/config/services.yaml');
    }
}

