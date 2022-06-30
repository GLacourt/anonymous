<?php

declare(strict_types=1);

namespace Anonymous;

use Anonymous\AnonymizerRegistryInterface;

/**
 * Class AnonymizerService
 */
class Anonymizer
{
    /** @var AnonymizerRegistryInterface $anonymizerRegistry */
    protected AnonymizerRegistryInterface $anonymizerRegistry;

    /** @var array $config */
    protected array $config = [];

    /**
     * AnonymizerService Constructor
     *
     * @param AnonymizerRegistryInterface $anonymizerRegistry
     * @param array                       $config
     */
    public function __construct(AnonymizerRegistryInterface $anonymizerRegistry, array $config = [])
    {
        $this->anonymizerRegistry = $anonymizerRegistry;
        $this->config             = $config;
    }

    /**
     * @return void
     */
    public function anonymize(): void
    {
        foreach ($this->config as $entity => $properties) {
            
        }
    }
}
