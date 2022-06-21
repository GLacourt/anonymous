<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Class AnonymousExtension
 */
class AnonymousExtension extends Extension
{
    /**
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);
    }
}
