<?php

namespace Nocturnal\EasyHtmlPurifier;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Nocturnal\EasyHtmlPurifier\Http\Middleware\EasyHtmlPurifier;

class EasyHtmlPurifierServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app instanceof LaravelApplication) {
            $this->publishes([$this->getConfigSource() => config_path('html_purifier.php')]);
        }
        $this->app->middleware([
            EasyHtmlPurifier::class
        ]);
    }

    public function register()
    {
        $this->mergeConfigFrom($this->getConfigSource(), 'html_purifier');

    }

    protected function getConfigSource()
    {
        return realpath(__DIR__.'/../config/html_purifier.php');
    }
}
