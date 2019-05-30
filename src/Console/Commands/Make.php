<?php
/**
 * Created by PhpStorm.
 * User: verronknowles
 * Date: 5/29/19
 * Time: 2:56 AM
 */

namespace DevelMe\DatabasePatch\Console\Commands;

use DevelMe\DatabasePatch\Patches\PatchCreator;
use Illuminate\Database\Console\Migrations\TableGuesser;
use Illuminate\Support\Str;
use Illuminate\Support\Composer;

class Make extends Base
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'make:patch {name : The name of the patch}
        {--insert= : The table to be inserted}
        {--table= : The table to migrate}
        {--path= : The location where the patch file should be created}
        {--realpath : Indicate any provided patch file paths are pre-resolved absolute paths}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new patch file';

    /**
     * The patch creator instance.
     *
     * @var \DevelMe\DatabasePatch\Patches\PatchCreator
     */
    protected $creator;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    /**
     * Create a new patch install command instance.
     *
     * @param  \DevelMe\DatabasePatch\Patches\PatchCreator  $creator
     * @param  \Illuminate\Support\Composer  $composer
     * @return void
     */
    public function __construct(PatchCreator $creator, Composer $composer)
    {
        parent::__construct();

        $this->creator = $creator;
        $this->composer = $composer;
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        // It's possible for the developer to specify the tables to modify in this
        // schema operation. The developer may also specify if this table needs
        // to be freshly created so we can create the appropriate patches.
        $name = Str::snake(trim($this->input->getArgument('name')));

        $table = $this->input->getOption('table');

        $insert = $this->input->getOption('insert') ?: false;

        // If no table was given as an option but a insert option is given then we
        // will use the "insert" option as the table name. This allows the devs
        // to pass a table name into this option as a short-cut for creating.
        if (! $table && is_string($insert)) {
            $table = $insert;

            $insert = true;
        }

        // Next, we will attempt to guess the table name if this the patch has
        // "insert" in the name. This will allow us to provide a convenient way
        // of creating patches that insert new tables for the application.
        if (! $table) {
            [$table, $insert] = TableGuesser::guess($name);
        }

        // Now we are ready to write the patch out to disk. Once we've written
        // the patch out, we will dump-autoload for the entire framework to
        // make sure that the patches are registered by the class loaders.
        $this->writePatch($name, $table, $insert);

        $this->composer->dumpAutoloads();
    }

    /**
     * Write the patch file to disk.
     *
     * @param  string $name
     * @param  string $table
     * @param  bool $insert
     * @return string
     * @throws \Exception
     */
    protected function writePatch($name, $table, $insert)
    {
        $file = pathinfo($this->creator->insert(
            $name, $this->getPatchPath(), $table, $insert
        ), PATHINFO_FILENAME);

        $this->line("<info>Created Patch:</info> {$file}");
    }

    /**
     * Get patch path (either specified by '--path' option or default location).
     *
     * @return string
     */
    protected function getPatchPath()
    {
        if (! is_null($targetPath = $this->input->getOption('path'))) {
            return ! $this->usingRealPath()
                ? $this->laravel->basePath().'/'.$targetPath
                : $targetPath;
        }

        return parent::getPatchPath();
    }

    /**
     * Determine if the given path(s) are pre-resolved "real" paths.
     *
     * @return bool
     */
    protected function usingRealPath()
    {
        return $this->input->hasOption('realpath') && $this->option('realpath');
    }
}