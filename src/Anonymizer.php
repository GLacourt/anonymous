<?php

declare(strict_types=1);

namespace Anonymous;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class AnonymizerService
 */
class Anonymizer
{
    private const PAGE_SIZE = 100;

    /** @var AnonymizerRegistryInterface $anonymizerRegistry */
    protected AnonymizerRegistryInterface $anonymizerRegistry;

    /** @var ManagerRegistry $managerRegistry */
    protected ManagerRegistry $managerRegistry;

    /** @var EntityManagerInterface $entityManager */
    protected EntityManagerInterface $entityManager;

    /** @var array $config */
    protected array $config = [];

    /**
     * Anonymizer constructor.
     *
     * @param AnonymizerRegistryInterface $anonymizerRegistry
     * @param ManagerRegistry             $managerRegistry
     * @param array                       $config
     */
    public function __construct(AnonymizerRegistryInterface $anonymizerRegistry, ManagerRegistry $managerRegistry, array $config = [])
    {
        $this->anonymizerRegistry = $anonymizerRegistry;
        $this->managerRegistry    = $managerRegistry;
        $this->config             = $config;
        $this->entityManager      = $managerRegistry->getManager('anonymous');
    }

    /**
     * @param SymfonyStyle $io
     *
     * @return void
     * @throws MappingException|ReflectionException
     */
    public function anonymize(SymfonyStyle $io): void
    {
        $this->disableEntityListeners();

        foreach ($this->config['mapping'] as $entity => $properties) {
            if ($this->entityManager->getMetadataFactory()->isTransient($entity)) {
                continue;
            }

            $this->disableMetadataEntityListeners($entity);

            $io->info(sprintf('Anonymize "%s" of entity "%s"', implode(', ', array_keys($properties)), $entity));

            if ($this->config['pagination']) {
                $this->paginate($io, $entity, $properties);

                return ;
            }

            $entities    = $this->entityManager->getRepository($entity)->findAll();
            $progressBar = $io->createProgressBar(count($entities));

            $progressBar->setFormat('debug');

            $this->processAnonymize($entities, $properties, $progressBar);

            $progressBar->finish();
        }
    }

    /**
     * @return void
     */
    private function disableEntityListeners(): void
    {
        $this->entityManager->getConfiguration()->getEntityListenerResolver()->clear();

        // Disable listeners handled by the event manager.
        foreach ($this->entityManager->getEventManager()->getListeners() as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof EventSubscriber) {
                    $this->entityManager->getEventManager()->removeEventSubscriber($listener);

                    continue;
                }

                $this->entityManager->getEventManager()->removeEventListener([$eventName], $listener);
            }
        }
    }

    /**
     * @param string $class
     *
     * @return void
     *
     * @throws MappingException
     * @throws ReflectionException
     */
    private function disableMetadataEntityListeners(string $class): void
    {
        // Disable listeners handled by the metadata.
        $metadata = $this->entityManager->getMetadataFactory()->getMetadataFor($class);
        foreach ($metadata->entityListeners as $event => $listeners) {
            $metadata->entityListeners[$event] = [];
        }

        $this->entityManager->getMetadataFactory()->setMetadataFor($class, $metadata);
    }

    /**
     * @param iterable    $entities
     * @param array       $properties
     * @param ProgressBar $progressBar
     *
     * @return void
     */
    private function processAnonymize(iterable $entities, array $properties, ProgressBar $progressBar): void
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($entities as $object) {
            foreach ($properties as $property => $anonymizer) {
                if ($accessor->isWritable($object, $property) && $this->anonymizerRegistry->has($anonymizer)) {
                    $anonymizedValue = $this->anonymizerRegistry->get($anonymizer)
                        ->anonymize($accessor->getValue($object, $property));

                    $accessor->setValue($object, $property, $anonymizedValue);
                }
            }

            $progressBar->advance();
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * @param SymfonyStyle $io
     * @param string       $entity
     * @param array        $properties
     *
     * @return void
     */
    private function paginate(SymfonyStyle $io, string $entity, array $properties = []): void
    {
        $pageSize = $this->config['page_size'];
        $dql      = sprintf('SELECT o FROM %s o', $this->entityManager->getClassMetadata($entity)->getName());
        $query    = $this->entityManager->createQuery($dql);

        $paginator  = new Paginator($query, false);
        $totalItems = count($paginator);
        $pagesCount = ceil($totalItems / $pageSize);

        $progressBar = $io->createProgressBar($totalItems);

        $progressBar->setFormat('debug');

        for ($i = 0; $i < $pagesCount; $i++) {
            $paginator
                ->getQuery()
                ->setFirstResult($pageSize * $i)
                ->setMaxResults($pageSize);

            $this->processAnonymize($paginator, $properties, $progressBar);
        }
    }
}
