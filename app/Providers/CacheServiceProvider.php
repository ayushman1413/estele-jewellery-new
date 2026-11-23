<?php

namespace App\Providers;

use App\Support\FallbackTaggedCache;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('cache', function (CacheManager $manager) {
            return new class($this->app) extends CacheManager
            {
                public function tags($names): mixed
                {
                    try {
                        return parent::tags($names);
                    } catch (\BadMethodCallException) {
                        return new FallbackTaggedCache((array) $names);
                    }
                }
            };
        });
    }
}
