<?php

namespace Anonymous\Loader\Exception;

use Exception;
use Symfony\Component\Process\Process;

/**
 * Class LoadFailed
 */
class LoadFailed extends Exception
{
    /**
     * @param Process $process
     *
     * @return static
     */
    public static function processDidNotEndSuccessfully(Process $process): static
    {
        $processOutput = static::formatProcessOutput($process);

        return new static("The dump process failed with a none successful exitcode.{$processOutput}");
    }

    /**
     * @param Process $process
     *
     * @return static
     */
    public static function loadFileWasNotCreated(Process $process): static
    {
        $processOutput = static::formatProcessOutput($process);

        return new static("The dumpfile could not be created.{$processOutput}");
    }

    /**
     * @param Process $process
     *
     * @return static
     */
    public static function loadFileWasEmpty(Process $process): static
    {
        $processOutput = static::formatProcessOutput($process);

        return new static("The created dumpfile is empty.{$processOutput}");
    }

    /**
     * @param Process $process
     *
     * @return string
     */
    protected static function formatProcessOutput(Process $process): string
    {
        $output       = $process->getOutput() ?: '<no output>';
        $errorOutput  = $process->getErrorOutput() ?: '<no output>';
        $exitCodeText = $process->getExitCodeText() ?: '<no exit text>';

        return <<<CONSOLE

            Exitcode
            ========
            {$process->getExitCode()}: {$exitCodeText}

            Output
            ======
            {$output}

            Error Output
            ============
            {$errorOutput}
            CONSOLE;
    }
}
