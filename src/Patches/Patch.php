<?php

namespace DevelMe\DatabasePatch\Patches;

/**
 * Class Patch
 * @package DevelMe\DatabasePatch\Patches
 */
abstract class Patch
{
    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $connection;

    /**
     * Enables, if supported, wrapping the migration within a transaction.
     *
     * @var bool
     */
    public $withinTransaction = true;

    /**
     * Get the migration connection name.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }
}