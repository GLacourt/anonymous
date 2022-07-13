<?php
declare(strict_types=1);

namespace Anonymous;

use Anonymous\DependencyInjection\Compiler\AnonymizerPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

/**
 * Class AnonymousBundle
 */
class AnonymousBundle extends Bundle
{
    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AnonymizerPass());
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
