<?php
/**
 * Created by PhpStorm.
 * User: verronknowles
 * Date: 5/28/19
 * Time: 11:15 PM
 */

namespace DevelMe\DatabasePatch\Providers;

use DevelMe\DatabasePatch\Patches\PatchCreator;
use DevelMe\DatabasePatch\Patches\Patchor;
use DevelMe\DatabasePatch\Repository\Patch;
use Illuminate\Support\ServiceProvider;

class PatchServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/patch.php' => config_path('patch.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \DevelMe\DatabasePatch\Console\Commands\Status::class,
                \DevelMe\DatabasePatch\Console\Commands\Install::class,
                \DevelMe\DatabasePatch\Console\Commands\Make::class,
                \DevelMe\DatabasePatch\Console\Commands\Patch::class,
                \DevelMe\DatabasePatch\Console\Commands\Rollback::class,
                \DevelMe\DatabasePatch\Console\Commands\Reset::class,
                \DevelMe\DatabasePatch\Console\Commands\Refresh::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/patch.php', 'patch'
        );

        $this->registerRepository();

        $this->registerPatchor();

        $this->registerCreator();
    }

    /**
     * Register the patch repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('patch.repository', function ($app) {
            $table = $app['config']['patch.table'];

            return new Patch($app['db'], $table);
        });

        $this->app->singleton(Patch::class, function ($app) {
            return $app['patch.repository'];
        });
    }

    /**
     * Register the patchor service.
     *
     * @return void
     */
    protected function registerPatchor()
    {
        // The patchor is responsible for actually running and rollback the patch
        // files in the application. We'll pass in our database connection resolver
        // so the patchor can resolve any of these connections when it needs to.
        $this->app->singleton('patchor', function ($app) {
            $repository = $app['patch.repository'];

            return new Patchor($repository, $app['db'], $app['files']);
        });

        $this->app->singleton(Patchor::class, function ($app) {
            return $app['patchor'];
        });
    }

    /**
     * Register the patch creator.
     *
     * @return void
     */
    protected function registerCreator()
    {
        $this->app->singleton('patch.creator', function ($app) {
            return new PatchCreator($app['files']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'patchor', 'patch.repository', 'patch.creator',
        ];
    }
}