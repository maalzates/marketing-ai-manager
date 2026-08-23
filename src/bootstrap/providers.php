<?php

use App\Modules\Core\CoreServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // One entry per module. Keep alphabetical; Core first because everything depends on it.
    CoreServiceProvider::class,
];
