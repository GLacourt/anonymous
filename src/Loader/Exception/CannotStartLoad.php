<?php

declare(strict_types=1);

namespace Anonymous\Loader\Exception;

use Exception;

/**
 * Class CannotStartLoad
 */
class CannotStartLoad extends Exception
{
    public static function emptyParameter(string $name): static
    {
        return new static("Parameter `{$name}` cannot be empty.");
    }
}
