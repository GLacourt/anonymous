<?php

declare(strict_types=1);

namespace Anonymous\Anonymizer;

use Faker\Factory;

use function Symfony\Component\String\u;

/**
 * Class EmailAnonymizer
 */
class EmailAnonymizer implements AnonymizerInterface
{
    /** @var array $emails */
    protected array $emails = [];

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function anonymize(mixed $value): mixed
    {
        $faker     = Factory::create('fr_FR');
        $email     = $faker->email();
        $increment = 1;

        while (in_array($email, $this->emails)) {
            $part  = u($email)->split('@', 2);
            $email = $part[0]->append((string)$increment)->append('@')->append($part[1])->toString();

            $increment++;
        }

        $this->emails[] = $email;

        return $email;
    }

}
