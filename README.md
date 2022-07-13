# Anonymous
> This project purpose's is to allow production database dump with anonymous data.

## Table of contents
* [Technologies](#requirements)
* [Setup](#setup)
* [Configs](#configs)


## Requirements
* PHP 8.0
* Postgresql with pg_dump
* MySql with mysqldump

## Setup

```bash
php composer require glacourt/anonymous
```

## Configs
```yaml
#config/packages/anonymous.yaml
anonymous:
  mapping:
    App\Entity\User:
      email: Anonymous\Anonymizer\EmailAnonymizer
```

## Annexes

List of Anonymizer available :

- Anonymous\Anonymizer\EmailAnonymizer

If you want to create you own Anonymizer you just have to create a class which extend Anonymous\Anonymizer\Anonymizer\Interface

eg :

```php
<?php

declare(strict_types=1);

namespace App\Anonymizer;

/**
 * Class DummyAnonymizer
 */
class DummyAnonymizer implements AnonymizerInterface
{
    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function anonymize(mixed $value): mixed
    {
        return str_shuffle($value);
    }
}

```
