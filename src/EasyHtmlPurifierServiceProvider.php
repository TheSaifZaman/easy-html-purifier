<?php

namespace Nocturnal\EasyHtmlPurifier;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Nocturnal\EasyHtmlPurifier\Http\Middleware\EasyHtmlPurifier;

class EasyHtmlPurifierServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(): void
    {
        if ($this->app instanceof LaravelApplication) {
            $this->publishes([$this->getConfigSource() => config_path('html_purifier.php')]);
        }
        $this->app->middleware([
            EasyHtmlPurifier::class
        ]);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigSource(), 'html_purifier');

    }

    /**
     * @return bool|string
     */
    protected function getConfigSource(): bool|string
    {
        return realpath(__DIR__.'/../config/html_purifier.php');
    }
}
