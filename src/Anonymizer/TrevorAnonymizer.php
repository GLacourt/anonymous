<?php

declare(strict_types=1);

namespace Anonymous\Anonymizer;

/**
 * Class TrevorAnonymizer
 */
class TrevorAnonymizer implements AnonymizerInterface
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function anonymize(mixed $value): mixed
    {
        return 'CROAR';
    }

}
