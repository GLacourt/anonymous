<?php

declare(strict_types=1);

namespace Anonymous\Loader;

use Anonymous\Loader\Exception\LoadFailed;
use Spatie\DbDumper\Compressors\Compressor;
use Spatie\DbDumper\Exceptions\CannotSetParameter;
use Spatie\DbDumper\Exceptions\DumpFailed;
use Symfony\Component\Process\Process;

/**
 * Class DbLoader
 */
abstract class DbLoader
{
    /** @var string $dbName */
    protected string $dbName = '';

    /** @var string $userName */
    protected string $userName = '';

    /** @var string $password */
    protected string $password = '';

    /** @var string $host */
    protected string $host = 'localhost';

    /** @var int $port */
    protected int $port = 5432;

    protected int $timeout = 0;

    /** @var string $loaderBinaryPath */
    protected string $loaderBinaryPath = '';

    /** @var string $dumpBinaryPath */
    protected string $dumpBinaryPath = '';

    protected array $extraOptions = [];

    protected array $extraOptionsAfterDbName = [];

    protected ?object $compressor = null;

    /**
     * @return static
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }

    /**
     * @param string $dbName
     *
     * @return $this
     */
    public function setDbName(string $dbName): self
    {
        $this->dbName = $dbName;

        return $this;
    }

    /**
     * @param string $userName
     *
     * @return $this
     */
    public function setUserName(string $userName): self
    {
        $this->userName = $userName;

        return $this;
    }

    /**
     * @param string $password
     *
     * @return $this
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @param string $host
     *
     * @return $this
     */
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param int $port
     *
     * @return $this
     */
    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $dumpBinaryPath
     *
     * @return $this
     */
    public function setDumpBinaryPath(string $dumpBinaryPath): self
    {
        if ($dumpBinaryPath !== '' && ! str_ends_with($dumpBinaryPath, '/')) {
            $dumpBinaryPath .= '/';
        }

        $this->dumpBinaryPath = $dumpBinaryPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getCompressorExtension(): string
    {
        return $this->compressor->useExtension();
    }

    /**
     * @param Compressor $compressor
     *
     * @return $this
     */
    public function useCompressor(Compressor $compressor): self
    {
        $this->compressor = $compressor;

        return $this;
    }

    /**
     * @param string $extraOption
     *
     * @return $this
     */
    public function addExtraOption(string $extraOption): self
    {
        if (! empty($extraOption)) {
            $this->extraOptions[] = $extraOption;
        }

        return $this;
    }

    /**
     * @param string $extraOptionAfterDbName
     *
     * @return $this
     */
    public function addExtraOptionAfterDbName(string $extraOptionAfterDbName): self
    {
        if (! empty($extraOptionAfterDbName)) {
            $this->extraOptionsAfterDbName[] = $extraOptionAfterDbName;
        }

        return $this;
    }

    /**
     * @param string $dumpFile
     *
     * @return void
     */
    abstract public function loadFromFile(string $dumpFile): void;

    /**
     * @param Process $process
     * @param string  $outputFile
     *
     * @return void
     * @throws LoadFailed
     */
    public function checkIfDumpWasSuccessFul(Process $process, string $outputFile): void
    {
        if (! $process->isSuccessful()) {
            throw LoadFailed::processDidNotEndSuccessfully($process);
        }

        if (! file_exists($outputFile)) {
            throw LoadFailed::loadFileWasNotCreated($process);
        }

        if (filesize($outputFile) === 0) {
            throw LoadFailed::loadFileWasEmpty($process);
        }
    }

    protected function getCompressCommand(string $command, string $dumpFile): string
    {
        $compressCommand = $this->compressor->useCommand();

        if ($this->isWindows()) {
            return "{$command} | {$compressCommand} > {$dumpFile}";
        }

        return "(((({$command}; echo \$? >&3) | {$compressCommand} > {$dumpFile}) 3>&1) | (read x; exit \$x))";
    }

    /**
     * @param string $command
     * @param string $dumpFile
     *
     * @return string
     */
    protected function loadFile(string $command, string $dumpFile): string
    {
        $dumpFile = sprintf('"%s"', addcslashes($dumpFile, '\\"'));

        // TODO uncompress command
//        if ($this->compressor) {
//            return $this->getCompressCommand($command, $dumpFile);
//        }

        return sprintf('%s < %s', $command, $dumpFile);
    }

    /**
     * @return string
     */
    protected function determineQuote(): string
    {
        return $this->isWindows() ? '"' : "'";
    }

    /**
     * @return bool
     */
    protected function isWindows(): bool
    {
        return str_starts_with(strtoupper(PHP_OS), 'WIN');
    }
}
