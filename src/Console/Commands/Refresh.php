<?php

namespace DevelMe\DatabasePatch\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class Refresh
 * @package DevelMe\DatabasePatch\Console\Commands
 */
class Refresh extends Command
{
    use ConfirmableTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'patch:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all patches';

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

        // Next we'll gather some of the options so that we can have the right options
        // to pass to the commands. This includes options such as which database to
        // use and the path to use for the patch. Then we'll run the command.
        $database = $this->input->getOption('database');

        $path = $this->input->getOption('path');

        // If the "step" option is specified it means we only want to rollback a small
        // number of patches before migrating again. For example, the user might
        // only rollback and repatch the latest four patches instead of all.
        $step = $this->input->getOption('step') ?: 0;

        if ($step > 0) {
            $this->runRollback($database, $path, $step);
        } else {
            $this->runReset($database, $path);
        }

        // The refresh command is essentially just a brief aggregate of a few other of
        // the patch commands and just provides a convenient wrapper to execute
        // them in succession. We'll also see if we need to re-seed the database.
        $this->call('patch', array_filter([
            '--database' => $database,
            '--path' => $path,
            '--realpath' => $this->input->getOption('realpath'),
            '--force' => true,
        ]));
    }

    /**
     * Run the rollback command.
     *
     * @param  string  $database
     * @param  string  $path
     * @param  int  $step
     * @return void
     */
    protected function runRollback($database, $path, $step)
    {
        $this->call('patch:rollback', array_filter([
            '--database' => $database,
            '--path' => $path,
            '--realpath' => $this->input->getOption('realpath'),
            '--step' => $step,
            '--force' => true,
        ]));
    }

    /**
     * Run the reset command.
     *
     * @param  string  $database
     * @param  string  $path
     * @return void
     */
    protected function runReset($database, $path)
    {
        $this->call('patch:reset', array_filter([
            '--database' => $database,
            '--path' => $path,
            '--realpath' => $this->input->getOption('realpath'),
            '--force' => true,
        ]));
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

            ['seeder', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder'],

            ['step', null, InputOption::VALUE_OPTIONAL, 'The number of patches to be reverted & re-run'],
        ];
    }
}