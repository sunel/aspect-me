<?php 

namespace Aspect\Provider;

use Aspect\Advice;
use Illuminate\Support\Str;
use Aspect\Command\Inspect;
use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider
{
	/**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.appect.inspect', function ($app) {
        	$inspect = new Inspect();
        	$inspect->setContainer($app);
        	$inspect->setCleanHandler(function() use ($app) {
				$app['files']->cleanDirectory(resource_path('generated/'));
        	});
        	$inspect->setCodeHandler(function($namespace, $fileName, $code) use ($app) {

        		$directory = 'generated/'.ltrim(str_replace(['\\', '_'], '/', $namespace), '/');        		
        		$app['files']->makeDirectory(resource_path($directory), 0755, true, true);
        		$app['files']->put(resource_path($directory.'/'.$fileName.'.php'), $code);

        		return true;

        	});
            return $inspect;
        });

        if ($this->app->runningInConsole()) {
	        $this->commands([
	        	'command.appect.inspect'
	        ]);
	    } else {
	    	$this->app->booting(function($app) {
		    	$this->controlTarget();	
		    });	
	    }
	    
    }

    protected function controlTarget()
    {
    	$contianer = $this->app;

    	$advices = collect(Advice::all());

    	$advices->keys()->each(function ($target) use($contianer) {
    		$contianer->bind($target, $target.'\\Proxy');
    	});
    }
}