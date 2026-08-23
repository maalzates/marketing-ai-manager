<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Enums;

enum IntegrationKind: string
{
    case API_KEY = 'api_key';
    case OAUTH = 'oauth';
}
