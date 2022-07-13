<?php

declare(strict_types=1);

namespace Anonymous\Loader\Platform;

use Anonymous\Loader\DbLoader;
use Anonymous\Loader\Exception\CannotStartLoad;
use Anonymous\Loader\Exception\FileNotFoundException;
use Anonymous\Loader\Exception\LoadFailed;
use Symfony\Component\Process\Process;

/**
 * Class PostgreSql
 */
class PostgreSql extends DbLoader
{
    /** @var false|resource $credentials */
    private mixed $credentials;

    /**
     * PostgreSql Constructor
     */
    public function __construct()
    {
        $this->port        = 5432;
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
            throw  new FileNotFoundException();
        }

        $process = $this->getProcess($dumpFile);

        $process->run();

        $this->checkIfDumpWasSuccessFul($process, $dumpFile);
    }

    /**
     * @param string $dumpFile
     *
     * @return Process
     */
    protected function getProcess(string $dumpFile): Process
    {
        $command = $this->getLoadCommand($dumpFile);

        $this->setContentsOfCredentialsFile();

        $envVars = $this->getEnvironmentVariablesForLoadCommand();

        return Process::fromShellCommandline($command, null, $envVars, null, $this->timeout);
    }

    /**
     * @param string $dumpFile
     *
     * @return string
     */
    protected function getLoadCommand(string $dumpFile): string
    {
        $quote = $this->determineQuote();

        $command = [
            "$quote{$this->loaderBinaryPath}psql$quote",
            "-U $this->userName",
            "-h $this->host",
            "-p $this->port",
        ];

        foreach ($this->extraOptions as $extraOption) {
            $command[] = $extraOption;
        }

        return $this->loadFile(implode(' ', $command), $dumpFile);
    }

    /**
     * @return $this
     */
    protected function setContentsOfCredentialsFile(): self
    {
        $contents = [
            $this->escapeCredentialEntry($this->host),
            $this->escapeCredentialEntry($this->port),
            $this->escapeCredentialEntry($this->dbName),
            $this->escapeCredentialEntry($this->userName),
            $this->escapeCredentialEntry($this->password),
        ];

        fwrite($this->credentials, implode(':', $contents));

        return $this;
    }

    /**
     * @return void
     * @throws CannotStartLoad
     */
    protected function guardAgainstIncompleteCredentials(): void
    {
        foreach (['userName', 'dbName', 'host'] as $requiredProperty) {
            if (empty($this->$requiredProperty)) {
                throw CannotStartLoad::emptyParameter($requiredProperty);
            }
        }
    }

    /**
     * @param string $entry
     *
     * @return string
     */
    protected function escapeCredentialEntry(mixed $entry): string
    {
        $entry = str_replace('\\', '\\\\', (string) $entry);

        return str_replace(':', '\\:', (string) $entry);
    }

    /**
     * @return array
     */
    protected function getEnvironmentVariablesForLoadCommand(): array
    {
        return [
            'PGPASSFILE' => $this->getPassFilePath(),
            'PGDATABASE' => $this->dbName,
        ];
    }

    /**
     * @return string
     */
    protected function getPassFilePath(): string
    {
        return stream_get_meta_data($this->credentials)['uri'];
    }
}
