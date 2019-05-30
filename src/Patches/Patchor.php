<?php
/**
 * Created by PhpStorm.
 * User: verronknowles
 * Date: 5/29/19
 * Time: 12:09 AM
 */

namespace DevelMe\DatabasePatch\Patches;

use DevelMe\DatabasePatch\Repository\Patch;
use \Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Console\OutputStyle;

class Patchor
{
    /**
     * The patch repository implementation.
     *
     * @var \DevelMe\DatabasePatch\Repository\Patch
     */
    protected $repository;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connection;

    /**
     * The paths to all of the patch files.
     *
     * @var array
     */
    protected $paths = [];

    protected $notes = [];

    /**
     * The output interface implementation.
     *
     * @var \Illuminate\Console\OutputStyle
     */
    protected $output;

    /**
     * Create a new patchor instance.
     *
     * @param  \DevelMe\DatabasePatch\Repository\Patch  $repository
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Patch $repository,
        Resolver $resolver,
        Filesystem $files)
    {
        $this->files = $files;
        $this->resolver = $resolver;
        $this->repository = $repository;
    }

    /**
     * Run the pending patches at a given path.
     *
     * @param  array|string  $paths
     * @param  array  $options
     * @return array
     */
    public function run($paths = [], array $options = [])
    {
        $this->notes = [];

        // Once we grab all of the patch files for the path, we will compare them
        // against the patches that have already been run for this package then
        // run each of the outstanding patches against a database connection.
        $files = $this->getPatchFiles($paths);

        $this->requireFiles($patches = $this->pendingPatches(
            $files, $this->repository->getRan()
        ));

        // Once we have all these patches that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each patch as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($patches, $options);

        return $patches;
    }

    /**
     * Get the patch files that have not yet run.
     *
     * @param  array  $files
     * @param  array  $ran
     * @return array
     */
    protected function pendingPatches($files, $ran)
    {
        return Collection::make($files)
            ->reject(function ($file) use ($ran) {
                return in_array($this->getPatchName($file), $ran);
            })->values()->all();
    }

    /**
     * Run an array of patches.
     *
     * @param  array  $patches
     * @param  array  $options
     * @return void
     */
    public function runPending(array $patches, array $options = [])
    {
        // First we will just make sure that there are any patches to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the patches have been run against this database system.
        if (count($patches) === 0) {
            $this->note('<info>Nothing to patch.</info>');

            return;
        }

        // Next, we will get the next batch number for the patches so we can insert
        // correct batch number in the database patches repository when we store
        // each patch's execution. We will also extract a few of the options.
        $batch = $this->repository->getNextBatchNumber();

        $pretend = $options['pretend'] ?? false;

        $step = $options['step'] ?? false;

        // Once we have the array of patches, we will spin through them and run the
        // patches "up" so the changes are made to the databases. We'll then log
        // that the patch was run so we don't repeat it next time we execute.
        foreach ($patches as $file) {
            $this->runUp($file, $batch, $pretend);

            if ($step) {
                $batch++;
            }
        }
    }

    /**
     * Run "up" a patch instance.
     *
     * @param  string  $file
     * @param  int     $batch
     * @param  bool    $pretend
     * @return void
     */
    protected function runUp($file, $batch, $pretend)
    {
        // First we will resolve a "real" instance of the patch class from this
        // patch file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $patch = $this->resolve(
            $name = $this->getPatchName($file)
        );

        if ($pretend) {
            $this->pretendToRun($patch, 'up');

            return;
        }

        $this->note("<comment>Patching:</comment> {$name}");

        $this->runPatch($patch, 'up');

        // Once we have run a patches class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a patch
        // in the application. A patch repository keeps the patch order.
        $this->repository->log($name, $batch);

        $this->note("<info>Patched:</info>  {$name}");
    }

    /**
     * Rollback the last patch operation.
     *
     * @param  array|string $paths
     * @param  array  $options
     * @return array
     */
    public function rollback($paths = [], array $options = [])
    {
        $this->notes = [];

        // We want to pull in the last batch of patches that ran on the previous
        // patch operation. We'll then reverse those patches and run each
        // of them "down" to reverse the last patch "operation" which ran.
        $patches = $this->getPatchesForRollback($options);

        if (count($patches) === 0) {
            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        return $this->rollbackPatches($patches, $paths, $options);
    }

    /**
     * Get the patches for a rollback operation.
     *
     * @param  array  $options
     * @return array
     */
    protected function getPatchesForRollback(array $options)
    {
        if (($steps = $options['step'] ?? 0) > 0) {
            return $this->repository->getPatches($steps);
        }

        return $this->repository->getLast();
    }

    /**
     * Rollback the given patches.
     *
     * @param  array  $patches
     * @param  array|string  $paths
     * @param  array  $options
     * @return array
     */
    protected function rollbackPatches(array $patches, $paths, array $options)
    {
        $rolledBack = [];

        $this->requireFiles($files = $this->getPatchFiles($paths));

        // Next we will run through all of the patches and call the "down" method
        // which will reverse each patch in order. This getLast method on the
        // repository already returns these patch's names in reverse order.
        foreach ($patches as $patch) {
            $patch = (object) $patch;

            if (! $file = Arr::get($files, $patch->patch)) {
                $this->note("<fg=red>Patch not found:</> {$patch->patch}");

                continue;
            }

            $rolledBack[] = $file;

            $this->runDown(
                $file, $patch,
                $options['pretend'] ?? false
            );
        }

        return $rolledBack;
    }

    /**
     * Rolls all of the currently applied patches back.
     *
     * @param  array|string $paths
     * @param  bool  $pretend
     * @return array
     */
    public function reset($paths = [], $pretend = false)
    {
        $this->notes = [];

        // Next, we will reverse the patch list so we can run them back in the
        // correct order for resetting this database. This will allow us to get
        // the database back into its "empty" state ready for the patches.
        $patches = array_reverse($this->repository->getRan());

        if (count($patches) === 0) {
            $this->note('<info>Nothing to rollback.</info>');

            return [];
        }

        return $this->resetPatches($patches, $paths, $pretend);
    }

    /**
     * Reset the given patches.
     *
     * @param  array  $patches
     * @param  array  $paths
     * @param  bool  $pretend
     * @return array
     */
    protected function resetPatches(array $patches, array $paths, $pretend = false)
    {
        // Since the getRan method that retrieves the patch name just gives us the
        // patch name, we will format the names into objects with the name as a
        // property on the objects so that we can pass it to the rollback method.
        $patches = collect($patches)->map(function ($m) {
            return (object) ['patch' => $m];
        })->all();

        return $this->rollbackPatches(
            $patches, $paths, compact('pretend')
        );
    }

    /**
     * Run "down" a patch instance.
     *
     * @param  string $file
     * @param  object $patch
     * @param  bool $pretend
     * @return void
     * @throws \Throwable
     */
    protected function runDown($file, $patch, $pretend)
    {
        // First we will get the file name of the patch so we can resolve out an
        // instance of the patch. Once we get an instance we can either run a
        // pretend execution of the patch or we can run the real patch.
        $instance = $this->resolve(
            $name = $this->getPatchName($file)
        );

        $this->note("<comment>Rolling back:</comment> {$name}");

        if ($pretend) {
            $this->pretendToRun($instance, 'down');

            return;
        }

        $this->runPatch($instance, 'down');

        // Once we have successfully run the patch "down" we will remove it from
        // the patch repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($patch);

        $this->note("<info>Rolled back:</info>  {$name}");
    }

    /**
     * Run a patch inside a transaction if the database supports it.
     *
     * @param  object  $patch
     * @param  string  $method
     * @return void
     *
     * @throws \Throwable
     */
    protected function runPatch($patch, $method)
    {
        $connection = $this->resolveConnection(
            $patch->getConnection()
        );

        $callback = function () use ($patch, $method) {
            if (method_exists($patch, $method)) {
                $patch->{$method}();
            }
        };

        $patch->withinTransaction ? $connection->transaction($callback) : $callback();
    }

    /**
     * Pretend to run the patches.
     *
     * @param  object  $patch
     * @param  string  $method
     * @return void
     */
    protected function pretendToRun($patch, $method)
    {
        foreach ($this->getQueries($patch, $method) as $query) {
            $name = get_class($patch);

            $this->note("<info>{$name}:</info> {$query['query']}");
        }
    }

    /**
     * Get all of the queries that would be run for a patch.
     *
     * @param  object  $patch
     * @param  string  $method
     * @return array
     */
    protected function getQueries($patch, $method)
    {
        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this patch.
        $db = $this->resolveConnection(
            $patch->getConnection()
        );

        return $db->pretend(function () use ($patch, $method) {
            if (method_exists($patch, $method)) {
                $patch->{$method}();
            }
        });
    }

    /**
     * Resolve a patch instance from a file.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
        $class = Str::studly(implode('_', array_slice(explode('_', $file), 4)));

        return new $class;
    }

    /**
     * Get all of the patch files in a given path.
     *
     * @param  string|array  $paths
     * @return array
     */
    public function getPatchFiles($paths)
    {
        return Collection::make($paths)->flatMap(function ($path) {
            return Str::endsWith($path, '.php') ? [$path] : $this->files->glob($path.'/*_*.php');
        })->filter()->sortBy(function ($file) {
            return $this->getPatchName($file);
        })->values()->keyBy(function ($file) {
            return $this->getPatchName($file);
        })->all();
    }

    /**
     * Require in all the patch files in a given path.
     *
     * @param  array   $files
     * @return void
     */
    public function requireFiles(array $files)
    {
        foreach ($files as $file) {
            $this->files->requireOnce($file);
        }
    }

    /**
     * Get the name of the patch.
     *
     * @param  string  $path
     * @return string
     */
    public function getPatchName($path)
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Register a custom patch path.
     *
     * @param  string  $path
     * @return void
     */
    public function path($path)
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }

    /**
     * Get all of the custom patch paths.
     *
     * @return array
     */
    public function paths()
    {
        return $this->paths;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setConnection($name)
    {
        if (! is_null($name)) {
            $this->resolver->setDefaultConnection($name);
        }

        $this->repository->setSource($name);

        $this->connection = $name;
    }

    /**
     * Resolve the database connection instance.
     *
     * @param  string  $connection
     * @return \Illuminate\Database\Connection
     */
    public function resolveConnection($connection)
    {
        return $this->resolver->connection($connection ?: $this->connection);
    }

    /**
     * Get the schema grammar out of a patch connection.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function getSchemaGrammar($connection)
    {
        if (is_null($grammar = $connection->getSchemaGrammar())) {
            $connection->useDefaultSchemaGrammar();

            $grammar = $connection->getSchemaGrammar();
        }

        return $grammar;
    }

    /**
     * Get the patch repository instance.
     *
     * @return \DevelMe\DatabasePatch\Repository\Patch
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Determine if the patch repository exists.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        return $this->repository->repositoryExists();
    }

    /**
     * Get the file system instance.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Set the output implementation that should be used by the console.
     *
     * @param  \Illuminate\Console\OutputStyle  $output
     * @return $this
     */
    public function setOutput(OutputStyle $output)
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Write a note to the conosle's output.
     *
     * @param  string  $message
     * @return void
     */
    protected function note($message)
    {
        if ($this->output) {
            $this->output->writeln($message);
        }
    }
}