<?php

declare(strict_types=1);

namespace Anonymous\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Class DumpCommand
 */
#[AsCommand(name: 'anonymous:dump', description: 'Dump database with anonymous data', aliases: ['an:du'])]
class DumpCommand extends Command
{
    /** @var ManagerRegistry $doctrine */
    protected ManagerRegistry $doctrine;

    /**
     * DumpCommand Constructor
     *
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        parent::__construct();

        $this->doctrine = $doctrine;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'The connection to use for this command')->addOption('if-not-exists', null, InputOption::VALUE_NONE, 'Don\'t trigger an error, when the database already exists');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $connectionName = $input->getOption('connection');
        if (empty($connectionName)) {
            $connectionName = $this->doctrine->getDefaultConnectionName();
        }

        $ifNotExists   = $input->getOption('if-not-exists');
        $connection    = $this->getConnection($connectionName);
        $driverOptions = [];
        $params        = $connection->getParams();

        if (isset($params['driverOptions'])) {
            $driverOptions = $params['driverOptions'];
        }

        if (isset($params['primary'])) {
            $params                  = $params['primary'];
            $params['driverOptions'] = $driverOptions;
        }

        if (isset($params['master'])) {
            $params                  = $params['master'];
            $params['driverOptions'] = $driverOptions;
        }

        $hasPath = isset($params['path']);
        $name    = $hasPath ? $params['path'] : ($params['dbname'] ?? false);
        if (!$name) {
            throw new InvalidArgumentException('Connection does not contain a "path" or "dbname" parameter and cannot be created.');
        }

        // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
        unset($params['dbname'], $params['path'], $params['url']);

        $tmpConnection = DriverManager::getConnection($params);
        $tmpConnection->connect();

        $shouldNotCreateDatabase = $ifNotExists && in_array($name, $tmpConnection->createSchemaManager()->listDatabases());

        // Only quote if we don't have a path
        if (!$hasPath) {
            $name = $tmpConnection->getDatabasePlatform()->quoteSingleIdentifier($name);
        }

        $error = false;
        try {
            if ($shouldNotCreateDatabase) {
                $output->writeln(sprintf('<info>Database <comment>%s</comment> for connection named <comment>%s</comment> already exists. Skipped.</info>', $name, $connectionName));
            } else {
                $tmpConnection->createSchemaManager()->createDatabase($name);
                $output->writeln(sprintf('<info>Created database <comment>%s</comment> for connection named <comment>%s</comment></info>', $name, $connectionName));
            }
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Could not create database <comment>%s</comment> for connection named <comment>%s</comment></error>', $name, $connectionName));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            $error = true;
        }

        $tmpConnection->close();

        return $error ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param string $name
     *
     * @return Connection
     */
    protected function getConnection(string $name): Connection
    {
        return $this->doctrine->getConnection($name);
    }
}
