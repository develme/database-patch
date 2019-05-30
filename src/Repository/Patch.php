<?php
/**
 * Created by PhpStorm.
 * User: verronknowles
 * Date: 5/28/19
 * Time: 11:36 PM
 */

namespace DevelMe\DatabasePatch\Repository;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\ConnectionResolverInterface;

/**
 * Class Patch
 * @package DevelMe\DatabasePatch\Repository
 */
class Patch
{
    /**
     * The name of the patch table
     *
     * @var string
     */
    private $table;

    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $connection;

    /**
     * The database connection resolver instance.
     * @var Resolver
     */
    private $resolver;

    /**
     * @param Resolver $resolver
     * @param string $table
     */
    public function __construct(Resolver $resolver, string $table)
    {
        $this->table = $table;
        $this->resolver = $resolver;
    }

    /**
     * Get the completed patches.
     *
     * @return array
     */
    public function getRan(): array
    {
        return $this->table()
            ->orderBy('batch', 'asc')
            ->orderBy('patch', 'asc')
            ->pluck('patch')
            ->all();
    }

    /**
     * Get list of patches.
     *
     * @param  int $steps
     * @return array
     */
    public function getPatches($steps)
    {
        return $this->table()
            ->where('batch', '>=', '1')
            ->orderBy('batch', 'desc')
            ->orderBy('patch', 'desc')
            ->take($steps)
            ->get()
            ->all();
    }

    /**
     * Get the last patch batch.
     *
     * @return array
     */
    public function getLast()
    {
        return $this->table()
            ->where('batch', $this->getLastBatchNumber())
            ->orderBy('patch', 'desc')
            ->get()
            ->all();
    }

    /**
     * Get the completed patches with their batch numbers.
     *
     * @return array
     */
    public function getPatchBatches()
    {
        return $this->table()
            ->orderBy('batch', 'asc')
            ->orderBy('patch', 'asc')
            ->pluck('batch', 'patch')
            ->all();
    }

    /**
     * Log that a patch was run.
     *
     * @param  string $file
     * @param  int $batch
     * @return void
     */
    public function log($file, $batch)
    {
        $this->table()->insert(['patch' => $file, 'batch' => $batch]);
    }

    /**
     * Remove a patch from the log.
     *
     * @param  object $patch
     * @return void
     */
    public function delete($patch)
    {
        $this->table()->where('patch', $patch->patch)->delete();
    }

    /**
     * Get the next patch batch number.
     *
     * @return int
     */
    public function getNextBatchNumber()
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last patch batch number.
     *
     * @return int
     */
    public function getLastBatchNumber()
    {
        return $this->table()->max('batch');
    }

    /**
     * Create the patch repository data store.
     *
     * @return void
     */
    public function createRepository()
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        $schema->create($this->table, function ($table) {
            // The patches table is responsible for keeping track of which of the
            // patches have actually run for the application. We'll create the
            // table to hold the migration file's path as well as the batch ID.
            $table->increments('id');
            $table->string('patch');
            $table->integer('batch');
        });
    }

    /**
     * Determine if the patch repository exists.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        return $schema->hasTable($this->table);
    }

    /**
     * Set the information source to gather data.
     *
     * @param  string $name
     * @return void
     */
    public function setSource($name)
    {
        $this->connection = $name;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function table(): \Illuminate\Database\Query\Builder
    {
        return $this->getConnection()->table($this->table)->useWritePdo();
    }

    /**
     * Get the connection resolver instance.
     *
     * @return ConnectionResolverInterface
     */
    public function getConnectionResolver(): ConnectionResolverInterface
    {
        return $this->resolver;
    }

    /**
     * Resolve the database connection instance.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->resolver->connection($this->connection);
    }
}