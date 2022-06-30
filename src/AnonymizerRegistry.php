<?php

declare(strict_types=1);

namespace Anonymous;

use Anonymous\Anonymizer\AnonymizerInterface;
use InvalidArgumentException;

/**
 * Class AnonymizerRegistry
 */
class AnonymizerRegistry implements AnonymizerRegistryInterface
{
    /** @var array $anonymizers */
    protected array $anonymizers = [];

    /**
     * @param AnonymizerInterface $anonymizer
     *
     * @return $this
     */
    public function add(AnonymizerInterface $anonymizer): AnonymizerRegistry
    {
        $this->anonymizers[get_class($anonymizer)] = $anonymizer;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return mixed
     */
    public function get(string $class): AnonymizerInterface
    {
        if (!array_key_exists($class, $this->anonymizers)) {
            throw new InvalidArgumentException(sprintf('Could not load anonymizer "%s" : anonymizer does not exist.', $class));
        }

        return $this->anonymizers[$class];
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function has(string $class): bool
    {
        return array_key_exists($class, $this->anonymizers);
    }
}
