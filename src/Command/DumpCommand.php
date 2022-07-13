<?php

declare(strict_types=1);

namespace Anonymous\Command;

use Anonymous\Anonymizer;
use Anonymous\Event\AnonymousDatabaseEvent;
use Anonymous\Loader\Exception\LoadFailed;
use Anonymous\Loader\Platform\MySql as MySqlLoader;
use Anonymous\Loader\Platform\PostgreSql as PostgreSqlLoader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;
use Spatie\DbDumper\Databases\MySql as MySqlDumper;
use Spatie\DbDumper\Databases\PostgreSql as PostgreSqlDumper;
use Spatie\DbDumper\Exceptions\CannotStartDump;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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

    /** @var Anonymizer $anonymizer */
    protected Anonymizer $anonymizer;

    /**
     * DumpCommand Constructor
     *
     * @param ManagerRegistry          $doctrine
     * @param EventDispatcherInterface $eventDispatcher
     * @param Anonymizer               $anonymizer
     */
    public function __construct(ManagerRegistry $doctrine, EventDispatcherInterface $eventDispatcher, Anonymizer $anonymizer)
    {
        parent::__construct();

        $this->doctrine        = $doctrine;
        $this->eventDispatcher = $eventDispatcher;
        $this->anonymizer      = $anonymizer;
    }

    /**
     * @return void
     */
    public function configure(): void
    {
        $this
            ->addOption('dump-file', 'd', InputOption::VALUE_OPTIONAL, 'The name of the dump file', 'dump.sql')
        ;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws CannotStartDump|Exception|LoadFailed|MappingException|ReflectionException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->createAnonymousDatabase($io)) {
            return self::FAILURE;
        }

        $io->info('Dumping default database in anonymous database...');

        $this->dumpDatabase();

        $io->info('Anonymizing...');

        $this->anonymizer->anonymize($io);

        $io->info('Dumping anonymous database...');

        $connection = $this->doctrine->getConnection('anonymous');
        $params     = $connection->getParams();
        $platform   = $connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            PostgreSqlDumper::create()
                ->setDbName($params['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->dumpToFile($input->getOption('dump-file'))
            ;
        }

        if ($platform instanceof MySQLPlatform) {
            MySqlDumper::create()
                ->setDbName($params['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->dumpToFile($input->getOption('dump-file'))
            ;
        }

        return self::SUCCESS;
    }

    /**
     * @param SymfonyStyle $io
     * @return bool
     * @throws Exception
     */
    protected function createAnonymousDatabase(SymfonyStyle $io): bool
    {
        /** @var Connection $connection */
        $connection = $this->doctrine->getConnection('anonymous');
        $params     = $connection->getParams();
        $name       = $params['dbname'];

        // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
        unset($params['dbname'], $params['path'], $params['url']);

        $tmpConnection = DriverManager::getConnection($params);
        $tmpConnection->connect();

        if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $result = $tmpConnection->executeQuery('SELECT rolcreatedb FROM pg_authid WHERE rolname = CURRENT_USER');

            if (!$result->fetchOne()) {
                $io->error(sprintf('The user %s has not the privilege to create database', $params['user']));
            }

            $result->free();
        }

        if ($connection->getDatabasePlatform() instanceof MySQLPlatform) {
            $result = $tmpConnection->executeQuery('SHOW GRANTS FOR CURRENT_USER');

            // TODO implement database creation voter.
        }

        $exist = in_array($name, $tmpConnection->createSchemaManager()->listDatabases());

        $success = true;
        try {
            if ($exist) {
                $io->info(sprintf('Database "%s" already exists. Droping...', $name));
                $this->dropAnonymousDatabase($io, $tmpConnection, $name);
            }

            $tmpConnection->createSchemaManager()->createDatabase($name);
            $io->info(sprintf('Created database "%s".', $name));
        } catch (Throwable $e) {
            $io->error(sprintf('Could not create database "%s".', $name));
            $io->error(sprintf('%s', $e->getMessage()));

            $success = false;
        }

        $tmpConnection->close();

        if ($success) {
            $connection = $this->doctrine->getConnection('anonymous');

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
     * @return void
     * @throws CannotStartDump
     * @throws Exception
     * @throws LoadFailed
     */
    protected function dumpDatabase(): void
    {
        /** @var Connection $connection */
        $connection           = $this->doctrine->getConnection();
        $params               = $connection->getParams();
        $platform             = $connection->getDatabasePlatform();
        $targetDatabaseParams = $this->doctrine->getConnection('anonymous')->getParams();

        if ($platform instanceof PostgreSQLPlatform) {
            $file = sprintf('%s/%s', sys_get_temp_dir(), 'pgdump.sql');

            PostgreSqlDumper::create()
                ->setDbName($params['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->dumpToFile($file)
            ;

            PostgreSqlLoader::create()
                ->setDbName($targetDatabaseParams['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->loadFromFile($file)
            ;
        }

        if ($platform instanceof MySQLPlatform) {
            $file = sprintf('%s/%s', sys_get_temp_dir(), 'msdump.sql');

            MySqlDumper::create()
                ->setDbName($params['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->dumpToFile($file)
            ;

            MySqlLoader::create()
                ->setDbName($targetDatabaseParams['dbname'])
                ->setUserName($params['user'])
                ->setPassword($params['password'])
                ->loadFromFile($file)
            ;
        }
    }
}
