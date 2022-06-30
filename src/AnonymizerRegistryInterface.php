<?php

declare(strict_types=1);

namespace Anonymous;

use Anonymous\Anonymizer\AnonymizerInterface;

/**
 * Interface AnonymizerRegistryInterface
 */
interface AnonymizerRegistryInterface
{
    /**
     * @param AnonymizerInterface $anonymizer
     *
     * @return AnonymizerRegistryInterface
     */
    public function add(AnonymizerInterface $anonymizer): AnonymizerRegistryInterface;

    /**
     * @param string $class
     *
     * @return AnonymizerInterface
     */
    public function get(string $class): AnonymizerInterface;

    /**
     * @param string $class
     *
     * @return bool
     */
    public function has(string $class): bool;
}
