<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Observers;

use App\Modules\Campaigns\Domain\Contracts\CampaignLaunchObserverInterface;

/**
 * The default when nothing is watching — a launch a human started over HTTP answers to
 * nobody. Bound with bindIf so a module that does watch wins whichever provider loads first.
 */
readonly class NullCampaignLaunchObserver implements CampaignLaunchObserverInterface
{
    public function launchSucceeded(int $accountId, int $launchReference, array $result): void {}

    public function launchFailed(int $accountId, int $launchReference, string $reason): void {}
}
