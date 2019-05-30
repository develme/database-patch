<?php
/**
 * Created by PhpStorm.
 * User: verronknowles
 * Date: 5/29/19
 * Time: 2:45 AM
 */

namespace DevelMe\DatabasePatch\Console\Commands;

use DevelMe\DatabasePatch\Repository\Patch;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;

class Install extends Base
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'patch:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the patch repository';

    /**
     * The repository instance.
     *
     * @var \DevelMe\DatabasePatch\Repository\Patch
     */
    protected $repository;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * Create a new patch install command instance.
     *
     * @param  \DevelMe\DatabasePatch\Repository\Patch $repository
     * @param Filesystem $filesystem
     */
    public function __construct(Patch $repository, Filesystem $filesystem)
    {
        parent::__construct();

        $this->repository = $repository;
        $this->filesystem = $filesystem;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->repository->setSource($this->input->getOption('database'));

        if (! $this->repository->repositoryExists()) {
            $this->repository->createRepository();
            $this->info('Patch table created successfully.');
        }

        $path = $this->getPatchPath();

        if (! $this->filesystem->isDirectory($path)) {
            $this->filesystem->makeDirectory($path);
            $this->info('Patch directory created successfully.');
        }
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
        ];
    }
}