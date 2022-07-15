# Anonymous
> The purpose of this bundle is to generate a dump of the database with anonymous data.

## Requirements
* PHP 8.0
* Postgresql with pg_dump
* MySql with mysqldump

## Setup

```shell
php composer require glacourt/anonymous
```

## Usage

```shell
php bin/console anonymous:dump
```

## Register an anomymizer

1. Using the configuration in anonymous.yaml (see [configs](#configs))
2. Using the attribute `Anonymize`

```php
#[Entity]
class User
{
    #[Anonymize(anonymizer: EmailAnonymizer::class)]
    protected string $email;
}
```

## Configs

If you want to use the config reference follow the next example:
```shell
php bin/console config:dump-reference anonymous
# Default configuration for extension with alias: "anonymous"
anonymous:

    # Enable or disable the pagination
    pagination:           false

    # Set the size of the pagination
    page_size:            100

    # Set the mapping with entities, properties and anonymizer to be used.
    mapping:

        # Prototype
        entity:               ~
```

```yaml
#config/packages/anonymous.yaml
anonymous:
  mapping:
    App\Entity\User:
      email: Anonymous\Anonymizer\EmailAnonymizer
```

## Anonymizer

List of the available anonymizers:

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
