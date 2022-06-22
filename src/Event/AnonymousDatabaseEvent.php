<?php

declare(strict_types=1);

namespace Anonymous\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Doctrine\DBAL\Connection;

/**
 * Class OrderPlacedEvent
 */
class AnonymousDatabaseEvent extends Event
{
    public const AFTER_CREATED = 'anonymous.database.after.created';
    public const AFTER_DROPED  = 'anonymous.database.after.droped';

    /** @var Connection $connection */
    protected Connection $connection;

    /**
     * AfterAnonymousDatabaseCreated Constructor
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
