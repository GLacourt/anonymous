<?php

declare(strict_types=1);

namespace Anonymous\Loader\Exception;

use Exception;

/**
 * Class CannotSetParameter
 */
class CannotSetParameter extends Exception
{
    /**
     * @param string $name
     * @param string $conflictName
     *
     * @return static
     */
    public static function conflictingParameters(string $name, string $conflictName): static
    {
        return new static("Cannot set `{$name}` because it conflicts with parameter `{$conflictName}`.");
    }
}
