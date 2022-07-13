<?php

declare(strict_types=1);

namespace Anonymous\Loader\Platform;

use Anonymous\Loader\DbLoader;
use Anonymous\Loader\Exception\CannotStartLoad;
use Anonymous\Loader\Exception\FileNotFoundException;
use Anonymous\Loader\Exception\LoadFailed;
use Symfony\Component\Process\Process;

/**
 * Class MySql
 */
class MySql extends DbLoader
{
    /** @var false|resource */
    private mixed $credentials;

    /**
     * MySql Constructor
     */
    public function __construct()
    {
        $this->port        = 3306;
        $this->credentials = tmpfile();
    }

    /**
     * @param string $dumpFile
     *
     * @return void
     * @throws LoadFailed|CannotStartLoad
     */
    public function loadFromFile(string $dumpFile): void
    {
        $this->guardAgainstIncompleteCredentials();

        if (!file_exists($dumpFile)) {
            throw new FileNotFoundException();
        }

        $process = $this->getProcess($dumpFile);

        $process->run();

        $this->checkIfDumpWasSuccessFul($process, $dumpFile);
    }

    /**
     * @param string $dumpFile
     * @return Process
     */
    protected function getProcess(string $dumpFile): Process
    {
        $this->setContentsOfCredentialsFile();

        $command = $this->getLoadCommand($dumpFile);

        return Process::fromShellCommandline($command, null, null, null, $this->timeout);
    }

    /**
     * @param string $dumpFile
     *
     * @return string
     */
    protected function getLoadCommand(string $dumpFile): string
    {
        $quote       = $this->determineQuote();
        $credentials = $this->getPassFilePath();

        $command = [
            "$quote{$this->dumpBinaryPath}mysql$quote",
            "--defaults-extra-file=\"$credentials\"",
            $this->dbName,
        ];

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        foreach ($this->extraOptionsAfterDbName as $extraOptionAfterDbName) {
            $command[] = $extraOptionAfterDbName;
        }

        return $this->loadFile(implode(' ', $command), $dumpFile);
    }

    /**
     * @return $this
     */
    protected function setContentsOfCredentialsFile(): self
    {
        $contents = [
            '[client]',
            "user = '$this->userName'",
            "password = '$this->password'",
            "port = '$this->port'",
        ];

        fwrite($this->credentials, implode(PHP_EOL, $contents));

        return $this;
    }

    /**
     * @return void
     * @throws CannotStartLoad
     */
    public function guardAgainstIncompleteCredentials(): void
    {
        foreach (['userName', 'host'] as $requiredProperty) {
            if (strlen($this->$requiredProperty) === 0) {
                throw CannotStartLoad::emptyParameter($requiredProperty);
            }
        }

        if (strlen($this->dbName) === 0) {
            throw CannotStartLoad::emptyParameter('dbName');
        }
    }

    /**
     * @return string
     */
    protected function getPassFilePath(): string
    {
        return stream_get_meta_data($this->credentials)['uri'];
    }
}
