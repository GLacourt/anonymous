<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection;

use App\Event\DisableListenerEvent;
use App\Event\DisableListenerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

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
        $loader        = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $configuration = new Configuration();

        $loader->load('services.xml');
    }
}
