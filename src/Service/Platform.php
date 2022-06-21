<?php
declare(strict_types=1);

namespace Anonymous\Service;

use Doctrine\Persistence\ManagerRegistry;

/**
 * Class Platform
 */
class Platform
{
    /** @var ManagerRegistry  */
    protected ManagerRegistry $doctrine;

    /**
     * Platform Constructor
     *
     * @param ManagerRegistry $doctrine
     */
    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @return mixed
     */
    public function getPlatform()
    {
        return $this->doctrine->getConnection('default')->getDatababsePlatform();
    }
}
