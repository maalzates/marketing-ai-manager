<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Enums;

enum IntegrationStatus: string
{
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case ERROR = 'error';
    case EXPIRED = 'expired';
}
