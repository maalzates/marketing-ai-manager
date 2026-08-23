<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class CampaignPersistenceFailedException extends ApiException
{
    // Inherits wrap(): CampaignPersistenceFailedException::wrap($throwable, context: [...])
}
