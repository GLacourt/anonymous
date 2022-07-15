<?php
declare(strict_types=1);

/**
 * Class Column
 *
 * PHP Version 8.0
 *
 * @category Component\List\Attribute
 *
 * @package  App\Component\List\Attribute
 *
 * @author   Arneo <dev@arneogroup.com>
 *
 * @licence  All right reserved
 *
 * @link     Null
 */
namespace Anonymous\Attribute;

use Attribute;

/**
 * Class Column
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Anonymize
{
    /** @var string $anonymizer */
    protected string $anonymizer;

    /**
     * Anonymize Constructor
     *
     * @param string $anonymizer
     */
    public function __construct(string $anonymizer)
    {
        $this->anonymizer = $anonymizer;
    }

    /**
     * @return string
     */
    public function getAnonymizer(): string
    {
        return $this->anonymizer;
    }
}
