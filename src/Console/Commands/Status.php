<?php

namespace DevelMe\DatabasePatch\Console\Commands;

use \DevelMe\DatabasePatch\Patches\Patchor;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class Status
 * @package DevelMe\DatabasePatch\Console\Commands
 */
class Status extends Base
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'patch:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of each patch';

    /**
     * The patchor instance.
     *
     * @var \DevelMe\DatabasePatch\Patches\Patchor
     */
    protected $patchor;

    /**
     * Create a new patch rollback command instance.
     *
     * @param  \DevelMe\DatabasePatch\Patches\Patchor $patchor
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
        $this->patchor->setConnection($this->option('database'));

        if (! $this->patchor->repositoryExists()) {
            return $this->error('Patch table not found.');
        }

        $ran = $this->patchor->getRepository()->getRan();

        $batches = $this->patchor->getRepository()->getPatchBatches();

        if (count($patches = $this->getStatusFor($ran, $batches)) > 0) {
            $this->table(['Ran?', 'Patch', 'Batch'], $patches);
        } else {
            $this->error('No patches found');
        }
    }

    /**
     * Get the status for the given ran patches.
     *
     * @param  array  $ran
     * @param  array  $batches
     * @return \Illuminate\Support\Collection
     */
    protected function getStatusFor(array $ran, array $batches)
    {
        return Collection::make($this->getAllPatchFiles())
            ->map(function ($patch) use ($ran, $batches) {
                $patchName = $this->patchor->getPatchName($patch);

                return in_array($patchName, $ran)
                    ? ['<info>Yes</info>', $patchName, $batches[$patchName]]
                    : ['<fg=red>No</fg=red>', $patchName];
            });
    }

    /**
     * Get an array of all of the patch files.
     *
     * @return array
     */
    protected function getAllPatchFiles()
    {
        return $this->patchor->getPatchFiles($this->getPatchPaths());
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

            ['path', null, InputOption::VALUE_OPTIONAL, 'The path to the patches files to use'],

            ['realpath', null, InputOption::VALUE_NONE, 'Indicate any provided patch file paths are pre-resolved absolute paths'],
        ];
    }
}