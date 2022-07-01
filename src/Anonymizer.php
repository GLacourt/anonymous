<?php

declare(strict_types=1);

namespace Anonymous;

use Doctrine\Persistence\ManagerRegistry;
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
     * AnonymizerService Constructor
     *
     * @param AnonymizerRegistryInterface $anonymizerRegistry
     * @param array                       $config
     */
    public function __construct(AnonymizerRegistryInterface $anonymizerRegistry, ManagerRegistry $managerRegistry, array $config = [])
    {
        $this->anonymizerRegistry = $anonymizerRegistry;
        $this->managerRegistry    = $managerRegistry;
        $this->config             = $config;
    }

    /**
     * @return void
     */
    public function getAnonymousConnection()
    {
        dd($this->managerRegistry->getConnectionNames());
    }

    /**
     * @return void
     */
    public function anonymize(): void
    {
        $entityManager = $this->managerRegistry->getManager('anonymous');
        
        $propertyAccessor = PropertyAccess::createPropertyAccessor();

        foreach ($this->config as $entity => $properties) {
            foreach ($entityManager->getRepository($entity)->findAll() as $object) {
                foreach ($properties as $property => $anonymizer) {
                    if ($propertyAccessor->isWritable($object, $property) && $this->anonymizerRegistry->has($anonymizer)) {
                        $anonymizedValue = $anonymizer->anonymize($propertyAccessor->getValue($object, $property));

                        $propertyAccessor->setValue($object, $property, $anonymizedValue);
                    }
                }
            }

            $entityManager->flush();
            $entityManager->clear();
        }
    }
}
