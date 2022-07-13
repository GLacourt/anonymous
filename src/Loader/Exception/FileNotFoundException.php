<?php

declare(strict_types=1);

namespace Anonymous\Loader\Exception;

use RuntimeException;
use Throwable;

/**
 * Class FileNotFoundException
 */
class FileNotFoundException extends RuntimeException
{
    public function __construct(string $message = null, int $code = 0, Throwable $previous = null)
    {
        $message = $message ?? 'File could not be found.';

        parent::__construct($message, $code, $previous);
    }
}
