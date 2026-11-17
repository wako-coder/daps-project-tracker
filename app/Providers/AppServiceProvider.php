<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Ids in URLs must look like ids, so junk never reaches a bigint query.
        foreach (['organization', 'project', 'task', 'user'] as $parameter) {
            Route::pattern($parameter, '[0-9]{1,18}');
        }
    }
}
