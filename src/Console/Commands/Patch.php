<?php

namespace DevelMe\DatabasePatch\Console\Commands;

use Illuminate\Console\ConfirmableTrait;
use DevelMe\DatabasePatch\Patches\Patchor;

/**
 * Class Patch
 * @package DevelMe\DatabasePatch\Console\Commands
 */
class Patch extends Base
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patch {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path= : The path to the patches files to be executed}
                {--realpath : Indicate any provided patch file paths are pre-resolved absolute paths}
                {--pretend : Dump the SQL queries that would be run}
                {--step : Force the patches to be run so they can be rolled back individually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the database patches';

    /**
     * The patchor instance.
     *
     * @var \DevelMe\DatabasePatch\Patches\Patchor
     */
    protected $patchor;

    /**
     * Create a new patch command instance.
     *
     * @param  \DevelMe\DatabasePatch\Patches\Patchor  $patchor
     * @return void
     */
    public function __construct(Patchor $patchor)
    {
        parent::__construct();

        $this->patchor = $patchor;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $this->prepareDatabase();

        // Next, we will check to see if a path option has been defined. If it has
        // we will use the path relative to the root of this installation folder
        // so that patches may be run for any path within the applications.
        $this->patchor->setOutput($this->output)
            ->run($this->getPatchPaths(), [
                'pretend' => $this->option('pretend'),
                'step' => $this->option('step'),
            ]);
    }

    /**
     * Prepare the patch database for running.
     *
     * @return void
     */
    protected function prepareDatabase()
    {
        $this->patchor->setConnection($this->option('database'));

        if (! $this->patchor->repositoryExists()) {
            $this->call('patch:install', array_filter([
                '--database' => $this->option('database'),
            ]));
        }
    }
}