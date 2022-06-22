<?php

declare(strict_types=1);

namespace Anonymous\Command;

use Anonymous\Event\AfterAnonymousDatabaseCreated;
use Anonymous\Event\AnonymousDatabaseEvent;
use Anonymous\Loader\Platform\PostgreSql as PostgreSqlLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Spatie\DbDumper\Databases\PostgreSql as PostgreSqlDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Class DumpCommand
 */
#[AsCommand(name: 'anonymous:dump', description: 'Dump database with anonymous data', aliases: ['an:du'])]
class DumpCommand extends Command
{
    /** @var ManagerRegistry $doctrine */
    protected ManagerRegistry $doctrine;

    /** @var EventDispatcherInterface $eventDispatcher */
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * DumpCommand Constructor
     *
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine, EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct();

        $this->doctrine        = $doctrine;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
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

        $connectionNames = array_keys($this->doctrine->getConnectionNames());
        $connectionName  = $connectionNames[0];

        if (count($connectionNames) > 1) {
            $helper   = $this->getHelper('question');
            $question = new ChoiceQuestion('Please select a connection', $connectionNames, array_search('default', $connectionNames) ?: 0);

            $question->setErrorMessage('Connection %s is invalid');

            $connectionName = $helper->ask($input, $output, $question);
        }

        if (!$this->createAnonymousDatabase($io, $connectionName)) {
            return self::FAILURE;
        }

        $this->dumpDatabase($connectionName);

        dd('TODO reverse data into new database');

        return self::SUCCESS;
    }

    /**
     * @param string $name
     *
     * @return array
     */
    protected function getConnectionInfo(string $name): array
    {
        $connection = $this->doctrine->getConnection($name);
        $params     = $this->initParams($connection);
        $name       = $this->getDatabaseName($connection, $params);

        return [$connection, $params, $name];
    }

    /**
     * @param string $name
     *
     * @return array
     */
    protected function getConnectionToAnonymousDatabase(string $connectionName): Connection
    {
        [$connection, $params, $name] = $this->getConnectionInfo($connectionName);

        $params['dbname'] = $name;
        unset($params['path'], $params['url']);

        return DriverManager::getConnection($params);
    }

    /**
     * @param Connection $connection
     *
     * @return array
     */
    protected function initParams(Connection $connection): array
    {
        $params = $connection->getParams();

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

        return $params;
    }

    /**
     * @param Connection $connection
     * @param array      $params
     *
     * @return string
     * @throws Exception
     */
    protected function getDatabaseName(Connection $connection, array $params): string
    {
        $hasPath = isset($params['path']);
        $name    = $hasPath ? $params['path'] : ($params['dbname'] ?? false);
        if (!$name) {
            throw new InvalidArgumentException('Connection does not contain a "path" or "dbname" parameter and cannot be created.');
        }

        return sprintf('%s_anonymous', $name);
    }

    /**
     * @param SymfonyStyle $io
     * @param string       $connectionName
     *
     * @return bool
     * @throws Exception
     */
    protected function createAnonymousDatabase(SymfonyStyle $io, string $connectionName): bool
    {
        [$connection, $params, $name] = $this->getConnectionInfo($connectionName);

        // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
        unset($params['dbname'], $params['path'], $params['url']);

        $tmpConnection = DriverManager::getConnection($params);
        $tmpConnection->connect();

        $exist = in_array($name, $tmpConnection->createSchemaManager()->listDatabases());

        $success = true;
        try {
            if ($exist) {
                $io->info(sprintf('Database "%s" from connection named "%s" already exists. Droping...', $name, $connectionName));
                $this->dropAnonymousDatabase($io, $tmpConnection, $name);
            }

            $tmpConnection->createSchemaManager()->createDatabase($name);
            $io->info(sprintf('Created database "%s" from connection named "%s".', $name, $connectionName));
        } catch (Throwable $e) {
            $io->error(sprintf('Could not create database %s from connection named %s', $name, $connectionName));
            $io->error(sprintf('%s', $e->getMessage()));

            $success = false;
        }

        $tmpConnection->close();

        if ($success) {
            $connection = $this->getConnectionToAnonymousDatabase($connectionName);

            $event = new AnonymousDatabaseEvent($connection);
            $this->eventDispatcher->dispatch($event, AnonymousDatabaseEvent::AFTER_CREATED);
        }

        return $success;
    }

    /**
     * @param SymfonyStyle $io
     * @param Connection   $connection
     * @param string       $name
     *
     * @return bool
     */
    protected function dropAnonymousDatabase(SymfonyStyle $io, Connection $connection, string $name): bool
    {
        $success = true;
        try {
            $connection->getSchemaManager()->dropDatabase($name);
            $io->info(sprintf('Dropped database %s.', $name));
        } catch (Throwable $e) {
            $io->error(sprintf('Could not drop database %s.', $name));
            $io->error($e->getMessage());

            $success = false;
        }

        if ($success) {
            $event = new AnonymousDatabaseEvent($connection);
            $this->eventDispatcher->dispatch($event, AnonymousDatabaseEvent::AFTER_DROPED);
        }

        return $success;
    }

    /**
     * @param string $connectionName
     *
     * @return void
     * @throws Exception
     * @throws ToolsException
     */
    protected function dumpDatabase(string $connectionName): void
    {
        /** @var Connection $connection */
        [$connection, $params, $name] = $this->getConnectionInfo($connectionName);
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            PostgreSqlDumper::create()
                ->setDbName($params['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->dumpToFile('pgdump.sql')
            ;

            PostgreSqlLoader::create()
                ->setDbName($name)
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->fileToLoad('pgdump.sql')
            ;
        }
    }

    /**
     * @param string $connectionName
     *
     * @return void
     */
    protected function copyDataToAnonymousDatabase(string $connectionName): void
    {
        /** @var Connection $from */
        $from                = $this->doctrine->getConnection($connectionName);
        $to                  = $this->getConnectionToAnonymousDatabase($connectionName);
        $platform            = $from->getDatabasePlatform();
        $schemaManager       = $from->createSchemaManager();
        $anonymousTablesList = $to->createSchemaManager()->listTableNames();

        $to->connect();

        $platform->supportsForeignKeyConstraints();
        $to->executeQuery('SET foreign_key_checks = 0');

        /** @var ClassMetadata $class */
        foreach ($schemaManager->listTableNames() as $tableName) {
            if (!in_array($tableName, $anonymousTablesList)) {
                continue;
            }

            $result  = $from->executeQuery(sprintf('SELECT * FROM %s', $tableName));
            $table   = $schemaManager->listTableDetails($tableName);
            $columns = $table->getColumns();

            $types = [];
            foreach ($columns as $column) {
                $type = $column->getType()->getBindingType();
                if (!$column->getNotnull()) {
                    $type |= ParameterType::NULL;
                }

                $types[$column->getName()] = $type;
            }

            if ($result->rowCount() > 0) {
                foreach ($result->fetchAllAssociative() as $row) {
                    //                    $to->insert($tableName, $row, $types);
                    try {
                        $to->insert($tableName, $row, $types);
                    } catch (Exception\DriverException $e) {
                        dd($tableName, $row, $types, $e->getQuery(), $e->getMessage());
                    }
                }
            }
        }
    }
}
