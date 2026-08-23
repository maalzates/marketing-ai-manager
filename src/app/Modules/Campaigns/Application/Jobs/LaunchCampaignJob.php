<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Jobs;

use App\Modules\Campaigns\Application\DTO\LaunchCampaignDTO;
use App\Modules\Campaigns\Application\Services\CampaignService;
use App\Modules\Campaigns\Domain\Contracts\CampaignLaunchObserverInterface;
use App\Modules\Core\Domain\Exceptions\ClientException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The door the proposal-acceptance path uses. It exists because the launch fetches each
 * image from this application's own signed media route to stream it to Meta, and a request
 * worker calling its own php-fpm pool can deadlock it.
 *
 * Not retried: the campaign, ad set and ad are created in sequence and each identifier is
 * persisted before the next call, so a resumed launch continues where it stopped — but that
 * resumption is a deliberate re-dispatch, never an automatic replay of a partial mutation.
 * One try also means a failure reaches failed() immediately rather than after a backoff, so
 * whatever authorised the launch is not left waiting on a verdict that already exists.
 */
class LaunchCampaignJob implements ShouldQueue
{
    use Queueable;

    private const string OPAQUE_FAILURE = 'El lanzamiento de la campaña falló por un error interno.';

    public int $tries = 1;

    public function __construct(private readonly LaunchCampaignDTO $dto) {}

    public function handle(CampaignService $service, CampaignLaunchObserverInterface $observer): void
    {
        $result = $service->launch($this->dto);

        if ($this->dto->launchReference !== null) {
            $observer->launchSucceeded($this->dto->accountId, $this->dto->launchReference, $result->toArray());
        }
    }

    /**
     * Laravel's own hook rather than a catch block, so the exception still reaches the
     * handler and is logged exactly once, at the level it chose for itself.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->dto->launchReference === null) {
            return;
        }

        // Resolved here because failed() is called on a deserialised job and gets no injection.
        app(CampaignLaunchObserverInterface::class)->launchFailed(
            $this->dto->accountId,
            $this->dto->launchReference,
            $exception instanceof ClientException ? $exception->getClientMessage() : self::OPAQUE_FAILURE,
        );
    }
}
