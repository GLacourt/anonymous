<?php

declare(strict_types=1);

namespace Anonymous;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class AnonymizerService
 */
class Anonymizer
{
    /** @var AnonymizerRegistryInterface $anonymizerRegistry */
    protected AnonymizerRegistryInterface $anonymizerRegistry;

    /** @var ManagerRegistry $managerRegistry */
    protected ManagerRegistry $managerRegistry;

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

    }

    /**
     * @param SymfonyStyle $io
     *
     * @return void
     * @throws MappingException|ReflectionException
     */
    public function anonymize(SymfonyStyle $io): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager    = $this->managerRegistry->getManager('anonymous');
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        $entityManager->getConfiguration()->getEntityListenerResolver()->clear();

        // Disable listeners handled by the event manager.
        foreach ($entityManager->getEventManager()->getListeners() as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof EventSubscriber) {
                    $entityManager->getEventManager()->removeEventSubscriber($listener);

                    continue;
                }

                $entityManager->getEventManager()->removeEventListener([$eventName], $listener);
            }
        }

        foreach ($this->config as $entity => $properties) {
            // Disable listeners handled by the metadata.
            $metadata = $entityManager->getMetadataFactory()->getMetadataFor($entity);
            foreach ($metadata->entityListeners as $event => $listeners) {
                $metadata->entityListeners[$event] = [];
            }

            $entityManager->getMetadataFactory()->setMetadataFor($entity, $metadata);

            $io->info(sprintf('Anonymize "%s" of entity "%s"', implode(', ', array_keys($properties)), $entity));

            $entities    = $entityManager->getRepository($entity)->findAll();
            $progressBar = $io->createProgressBar(count($entities));

            $progressBar->setFormat('debug');

            foreach ($entityManager->getRepository($entity)->findAll() as $object) {
                foreach ($properties as $property => $anonymizer) {
                    if ($propertyAccessor->isWritable($object, $property) && $this->anonymizerRegistry->has($anonymizer)) {
                        $anonymizedValue = $this->anonymizerRegistry->get($anonymizer)
                            ->anonymize($propertyAccessor->getValue($object, $property));

                        $propertyAccessor->setValue($object, $property, $anonymizedValue);
                    }
                }

                $progressBar->advance();
            }

            $progressBar->finish();

            $entityManager->flush();
            $entityManager->clear();
        }
    }
}
