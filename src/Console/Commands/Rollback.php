<?php

namespace DevelMe\DatabasePatch\Console\Commands;

use Illuminate\Console\ConfirmableTrait;
use DevelMe\DatabasePatch\Patches\Patchor;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class Rollback
 * @package DevelMe\DatabasePatch\Console\Commands
 */
class Rollback extends Base
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'patch:rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback the last database patch';

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

        $this->patchor->setOutput($this->output)->rollback(
            $this->getPatchPaths(), [
                'pretend' => $this->option('pretend'),
                'step' => (int) $this->option('step'),
            ]
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

            ['path', null, InputOption::VALUE_OPTIONAL, 'The path to the patches files to be executed'],

            ['realpath', null, InputOption::VALUE_NONE, 'Indicate any provided patch file paths are pre-resolved absolute paths'],

            ['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run'],

            ['step', null, InputOption::VALUE_OPTIONAL, 'The number of patches to be reverted'],
        ];
    }
}