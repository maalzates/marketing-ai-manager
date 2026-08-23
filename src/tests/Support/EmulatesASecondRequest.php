<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * Laravel caches the controller instance on its Route object, and one test process serves
 * every request a test method makes — where php-fpm hands each request a fresh process.
 *
 * A test that drives two accounts through the same endpoint therefore has to drop those
 * cached controllers between requests, or the second caller is answered by the first
 * caller's controller, still holding the first account's context.
 */
trait EmulatesASecondRequest
{
    protected function betweenRequests(): void
    {
        collect(Router::getRoutes()->getRoutes())
            ->each(static fn (Route $route) => $route->flushController());
    }
}
