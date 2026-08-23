<?php

declare(strict_types=1);

namespace App\Modules\Settings\Domain\Enums;

enum SettingScope: string
{
    case GLOBAL = 'global';
    case ACCOUNT = 'account';
    case STRATEGY = 'strategy';
}
