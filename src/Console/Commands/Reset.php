<?php
/**
 * Created by PhpStorm.
 * User: verronknowles
 * Date: 5/29/19
 * Time: 3:38 AM
 */

namespace DevelMe\DatabasePatch\Console\Commands;

use Illuminate\Console\ConfirmableTrait;
use DevelMe\DatabasePatch\Patches\Patchor;
use Symfony\Component\Console\Input\InputOption;

class Reset extends Base
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'patch:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback all database patches';

    /**
     * The patchor instance.
     *
     * @var \DevelMe\DatabasePatch\Patches\Patchor
     */
    protected $patchor;

    /**
     * Create a new patch rollback command instance.
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

        $this->patchor->setConnection($this->option('database'));

        // First, we'll make sure that the patch table actually exists before we
        // start trying to rollback and re-run all of the patches. If it's not
        // present we'll just bail out with an info message for the developers.
        if (! $this->patchor->repositoryExists()) {
            $this->comment('Patch table not found.');

            return;
        }

        $this->patchor->setOutput($this->output)->reset(
            $this->getPatchPaths(), $this->option('pretend')
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use'],

            ['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production'],

            ['path', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The path(s) to the patches files to be executed'],

            ['realpath', null, InputOption::VALUE_NONE, 'Indicate any provided patch file paths are pre-resolved absolute paths'],

            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run'],
        ];
    }
}