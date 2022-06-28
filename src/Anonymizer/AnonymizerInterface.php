<?php

declare(strict_types=1);

namespace Anonymous\Anonymizer;

/**
 * Interface AnonymizerInterface
 */
interface AnonymizerInterface
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function anonymize(mixed $value): mixed;
}
