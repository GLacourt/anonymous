<?php
declare(strict_types=1);

namespace Anonymous;

use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

/**
 * Class AnonymousBundle
 */
class AnonymousBundle extends Bundle
{
    /**
     * @return string
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
