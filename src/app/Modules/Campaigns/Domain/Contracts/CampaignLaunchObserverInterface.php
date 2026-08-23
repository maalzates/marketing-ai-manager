<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\Contracts;

/**
 * Told how a queued launch ended, so whatever authorised it can stop waiting.
 *
 * The port lives here rather than in the authorising module because that module already
 * depends on Campaigns — declaring it the other way round would close a dependency cycle.
 * For the same reason it cannot name what authorised the launch: the caller passes an
 * opaque reference in with the launch and gets it back here, and a launch with no reference
 * is simply never reported.
 */
interface CampaignLaunchObserverInterface
{
    /**
     * @param  array<string, mixed>  $result
     */
    public function launchSucceeded(int $accountId, int $launchReference, array $result): void;

    public function launchFailed(int $accountId, int $launchReference, string $reason): void;
}
