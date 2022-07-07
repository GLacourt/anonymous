<?php

declare(strict_types=1);

namespace Anonymous\DependencyInjection\Compiler;

use Anonymous\AnonymizerRegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\ImportDoctrineCommand;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Exception;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AnonymizerPass
 */
class AnonymizerPass extends DoctrineExtension implements CompilerPassInterface
{
    /** @var string */
    protected string $defaultConnection;

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(AnonymizerRegistryInterface::class)) {
            return;
        }

        $definition  = $container->findDefinition(AnonymizerRegistryInterface::class);
        $anonymizers = $container->findTaggedServiceIds('anonymous.anonymizer');

        foreach ($anonymizers as $id => $tags) {
            $definition->addMethodCall('add', [new Reference($id)]);
        }

        $this->load($this->getConfigs(), $container);
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config        = $this->processConfiguration($configuration, $configs);

        if (! empty($config['dbal'])) {
            $this->dbalLoad($config['dbal'], $container);
        }

        if (empty($config['orm'])) {
            return;
        }

        if (empty($config['dbal'])) {
            throw new LogicException('Configuring the ORM layer requires to configure the DBAL layer as well.');
        }

        $this->ormLoad($config['orm'], $container);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @throws Exception
     */
    protected function dbalLoad(array $config, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new \Symfony\Component\Config\FileLocator(__DIR__.'/../../../bundles/DoctrineBundle/Resources/config'));
        $loader->load('dbal.xml');

        dump('GOOD LOADER WIN WIN');

        if (class_exists(ImportCommand::class)) {
            $container->register('doctrine.database_import_command', ImportDoctrineCommand::class)
                      ->addTag('console.command', ['command' => 'doctrine:database:import']);
        }

        if (empty($config['default_connection'])) {
            $keys                         = array_keys($config['connections']);
            $config['default_connection'] = reset($keys);
        }

        $this->defaultConnection = $config['default_connection'];

        $container->setAlias('database_connection', sprintf('doctrine.dbal.%s_connection', $this->defaultConnection));
        $container->getAlias('database_connection')->setPublic(true);
        $container->setAlias('doctrine.dbal.event_manager', new Alias(sprintf('doctrine.dbal.%s_connection.event_manager', $this->defaultConnection), false));

        $container->setParameter('doctrine.dbal.connection_factory.types', $config['types']);

        $connections = [];

        foreach (array_keys($config['connections']) as $name) {
            $connections[$name] = sprintf('doctrine.dbal.%s_connection', $name);
        }

        $container->setParameter('doctrine.connections', $connections);
        $container->setParameter('doctrine.default_connection', $this->defaultConnection);

        $connWithLogging   = [];
        $connWithProfiling = [];
        $connWithBacktrace = [];
        foreach ($config['connections'] as $name => $connection) {
            if ($connection['logging']) {
                $connWithLogging[] = $name;
            }

            if ($connection['profiling']) {
                $connWithProfiling[] = $name;

                if ($connection['profiling_collect_backtrace']) {
                    $connWithBacktrace[] = $name;
                }
            }

            $this->loadDbalConnection($name, $connection, $container);
        }

        /** @psalm-suppress UndefinedClass */
        $container->registerForAutoconfiguration(MiddlewareInterface::class)->addTag('doctrine.middleware');

        if (PHP_VERSION_ID >= 80000 && method_exists(ContainerBuilder::class, 'registerAttributeForAutoconfiguration')) {
            $container->registerAttributeForAutoconfiguration(AsMiddleware::class, static function (ChildDefinition $definition, AsMiddleware $attribute) {
                if ($attribute->connections === []) {
                    $definition->addTag('doctrine.middleware');

                    return;
                }

                foreach ($attribute->connections as $connName) {
                    $definition->addTag('doctrine.middleware', ['connection' => $connName]);
                }
            });
        }
//        $this->useMiddlewaresIfAvailable($container, $connWithLogging, $connWithProfiling, $connWithBacktrace);
    }

//    protected function loadAnonymousManager(ContainerBuilder $container)
//    {
//        $config = [
//            "auto_generate_proxy_classes" => false,
//            "proxy_dir"                   => "%kernel.cache_dir%/doctrine/orm/Proxies",
//            "proxy_namespace"             => "Proxies",
//            "default_entity_manager"      => 'default',
//        ];
//
//        $config['entity_managers'] =  [
//            "default"   => [
//                "connection" => "default",
//                "mappings"   => [
//                    "App" => [
//                        "is_bundle" => false,
//                        "dir"       => '%kernel.project_dir%/src/Entity',
//                        "prefix"    => "App\Entity",
//                        "alias"     => "App",
//                        "mapping"   => true,
//                    ],
//                ],
//                "query_cache_driver"  => [
//                    "type" => null,
//                ],
//                "result_cache_driver" => [
//                    "type" => null,
//                ],
//                "class_metadata_factory_name" => "Doctrine\ORM\Mapping\ClassMetadataFactory",
//                "default_repository_class"    => "Doctrine\ORM\EntityRepository",
//                "auto_mapping"                => false,
//                "naming_strategy"             => "doctrine.orm.naming_strategy.default",
//                "quote_strategy"              => "doctrine.orm.quote_strategy.default",
//                "entity_listener_resolver"    => null,
//                "repository_factory"          => "doctrine.orm.container_repository_factory",
//                "schema_ignore_classes"       => [],
//                "hydrators"                   => [],
//                "filters"                     => [],
//            ],
//            "anonymous" => [
//                "connection"                  => "anonymous",
//                "mappings"                    => [
//                    "Anonymous" => [
//                        "is_bundle" => false,
//                        "dir"       => '%kernel.project_dir%/src/Entity',
//                        "prefix"    => "App\Entity",
//                        "alias"     => "Anonymous",
//                        "mapping"   => true,
//                    ],
//                ],
//                "query_cache_driver"          => [
//                    "type" => null,
//                ],
//                "result_cache_driver"         => [
//                    "type" => null,
//                ],
//                "class_metadata_factory_name" => "Doctrine\ORM\Mapping\ClassMetadataFactory",
//                "default_repository_class"    => "Doctrine\ORM\EntityRepository",
//                "auto_mapping"                => false,
//                "naming_strategy"             => "doctrine.orm.naming_strategy.default",
//                "quote_strategy"              => "doctrine.orm.quote_strategy.default",
//                "entity_listener_resolver"    => null,
//                "repository_factory"          => "doctrine.orm.container_repository_factory",
//                "schema_ignore_classes"       => [],
//                "hydrators"                   => [],
//                "filters"                     => [],
//            ],
//        ];
//
//        $managers = [
//            'default'   => 'doctrine.orm.default_entity_manager',
//            'anonymous' => 'doctrine.orm.anonymous_entity_manager',
//        ];
//
//        $container->setParameter('doctrine.orm.entity_managers', $managers);
//        $container->setParameter('doctrine.default_entity_manager', $config['default_entity_manager']);
//
//        $options = ['auto_generate_proxy_classes', 'proxy_dir', 'proxy_namespace'];
//
//        foreach ($options as $key) {
//            $container->setParameter('doctrine.orm.' . $key, $config[$key]);
//        }
//
//        $container->setAlias('doctrine.orm.entity_manager', $defaultEntityManagerDefinitionId = sprintf('doctrine.orm.%s_entity_manager', $config['default_entity_manager']));
//        $container->getAlias('doctrine.orm.entity_manager')->setPublic(true);
//    }

//    /**
//     * @param ContainerBuilder $container
//     *
//     * @return void
//     */
//    protected function loadAnonymousConnection(ContainerBuilder $container): void
//    {
//        $anonymousId = 'doctrine.dbal.anonymous_connection';
//        $connections = $container->getParameter('doctrine.connections');
//        $connections['anonymous'] = $anonymousId;
//
//        $container->setParameter('doctrine.connections', $connections);
//
//        $configuration = $container->setDefinition('doctrine.dbal.anonymous_connection.configuration', new ChildDefinition('doctrine.dbal.connection.configuration'));
//        $default = $container->getDefinition('doctrine.dbal.default_connection');
//
//        $default = [
//            'driver'   => 'pdo_pgsql',
//            'host'     => 'localhost',
//            'user'     => 'guillaume',
//            'password' => 'root',
//            'port'     => null,
//            'dbname'   => 'ouibikeapi_anonymous',
//        ];
//
//        $container->setDefinition('doctrine.dbal.anonymous_connection.event_manager', new ChildDefinition('doctrine.dbal.connection.event_manager'));
//
//        $def = $container
//            ->setDefinition($anonymousId, new ChildDefinition('doctrine.dbal.connection'))
//            ->setPublic(true)
//            ->setArguments([
//                $default,
//                new Reference('doctrine.dbal.anonymous_connection.configuration'),
//                new Reference('doctrine.dbal.anonymous_connection.event_manager'),
//                [],
//            ]);
//
//        // NOT USEFUL TO GET ANONYMOUS DATABASE
//        //        $container
//        //            ->registerAliasForArgument($connectionId, Connection::class, 'anonymousConnection')
//        //            ->setPublic(false);
//        //
//        //        $container->setDefinition(
//        //            ManagerRegistryAwareConnectionProvider::class,
//        //            new Definition(ManagerRegistryAwareConnectionProvider::class, [$container->getDefinition('doctrine')])
//        //        );
//    }

    /**
     * @return array[]
     */
    protected function getConfigs(): array
    {
        return [
            [
                "dbal" => [
                    "default_connection" => "anonymous",
                    "connections"        => [
                        "default"   => [
                            "url"            => '%env(resolve:DATABASE_URL)%',
                            "driver"         => "pdo_mysql",
                            "host"           => '%env(resolve:DATABASE_HOST)%',
                            "user"           => '%env(resolve:DATABASE_URL)%',
                            "password"       => '%env(resolve:DATABASE_PASSWORD)%',
                            "dbname"         => '%env(resolve:DATABASE_NAME)%',
                            "server_version" => "5.7",
                            "charset"        => "utf8mb4",
                        ],
                        "anonymous" => [
                            "url"            => '%env(resolve:DATABASE_URL)%_anonymous',
                            "driver"         => "pdo_mysql",
                            "host"           => '%env(resolve:DATABASE_HOST)%',
                            "user"           => '%env(resolve:DATABASE_URL)%',
                            "password"       => '%env(resolve:DATABASE_PASSWORD)%',
                            "dbname"         => '%env(resolve:DATABASE_NAME)%_anonymous',
                            "server_version" => "5.7",
                            "charset"        => "utf8mb4",
                        ],
                    ],
                ],
                "orm"  => [
                    "entity_managers" => [
                        "default"   => [
                            "connection" => "default",
                            "mappings"   => [
                                "App" => [
                                    "is_bundle" => false,
                                    "dir"       => '%kernel.project_dir%/src/Entity',
                                    "prefix"    => "App\Entity",
                                    "alias"     => "App",
                                ],
                            ],
                        ],
                        "anonymous" => [
                            "connection" => "anonymous",
                            "mappings"   => [
                                "Anonymous" => [
                                    "is_bundle" => false,
                                    "dir"       => '%kernel.project_dir%/src/Entity',
                                    "prefix"    => "App\Entity",
                                    "alias"     => "Anonymous",
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
