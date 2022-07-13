<?php

declare(strict_types=1);

namespace Anonymous;

use Closure;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

use function Symfony\Component\String\u;

class AnonymousEnvVarProcessor implements EnvVarProcessorInterface
{
    /**
     * @param string   $prefix
     * @param string   $name
     * @param Closure $getEnv
     *
     * @return mixed|void
     */
    public function getEnv(string $prefix, string $name, Closure $getEnv)
    {
        $env             = $getEnv($name);
        $currentDbName   = u($env)->afterLast('/')->before('?')->toString();
        $anonymousDbName = sprintf('%s_anonymous', $currentDbName);

        return str_replace($currentDbName, $anonymousDbName, $env);
    }

    /**
     * @return string[]
     */
    public static function getProvidedTypes(): array
    {
        return [
            'anonymous' => 'string',
        ];
    }

}
