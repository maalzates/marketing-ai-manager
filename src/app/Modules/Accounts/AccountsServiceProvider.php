<?php

declare(strict_types=1);

namespace App\Modules\Accounts;

use App\Modules\Accounts\Domain\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Accounts\Infrastructure\Repositories\AccountRepository;
use App\Modules\Accounts\Infrastructure\Repositories\RoleRepository;
use Illuminate\Support\ServiceProvider;

class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
    }
}
