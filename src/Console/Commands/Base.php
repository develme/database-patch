<?php

namespace DevelMe\DatabasePatch\Console\Commands;

use Illuminate\Console\Command;

/**
 * Class Base
 * @package DevelMe\DatabasePatch\Console\Commands
 */
abstract class Base extends Command
{
    /**
     * The pacthor instance.
     *
     * @var \DevelMe\DatabasePatch\Patches\Patchor
     */
    protected $patchor;

    /**
     * Get all of the patch paths.
     *
     * @return array
     */
    protected function getPatchPaths()
    {
        // Here, we will check to see if a path option has been defined. If it has we will
        // use the path relative to the root of the installation folder so our database
        // patches may be run for any customized path from within the application.
        if ($this->input->hasOption('path') && $this->option('path')) {
            return collect($this->option('path'))->map(function ($path) {
                return ! $this->usingRealPath()
                    ? $this->laravel->basePath().'/'.$path
                    : $path;
            })->all();
        }

        return array_merge(
            $this->patchor->paths(), [$this->getPatchPath()]
        );
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

    /**
     * Get the path to the patch directory.
     *
     * @return string
     */
    protected function getPatchPath()
    {
        return $this->laravel->databasePath().DIRECTORY_SEPARATOR.'patches';
    }
}