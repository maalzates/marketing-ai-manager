<?php

declare(strict_types=1);

use App\Modules\Campaigns\Application\Jobs\DispatchCampaignMetricsSyncJob;
use App\Modules\Competitors\Application\Jobs\SyncActiveCompetitorsJob;
use App\Modules\Content\Application\Jobs\DispatchAudienceSnapshotsJob;
use App\Modules\Content\Application\Jobs\DispatchDueContentJob;
use App\Modules\Content\Application\Jobs\ImportRecentOwnMetricsJob;
use App\Modules\Integrations\Application\Jobs\CheckIntegrationHealthJob;
use App\Modules\Reporting\Application\Jobs\DispatchGuardianJob;
use App\Modules\Reporting\Application\Jobs\EvaluateExpiredExperimentsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled work runs at night, before the user's day, and each dispatcher skips accounts and
// strategies with nothing active — so an idle account costs no LLM call and no provider call.

Schedule::job(new CheckIntegrationHealthJob)->dailyAt('03:00');

Schedule::job(new SyncActiveCompetitorsJob)->dailyAt('03:30');

// Metrics land before the guardián reads them; a guardián running on yesterday's numbers
// would raise anomalies that already resolved.
Schedule::job(new DispatchCampaignMetricsSyncJob)->hourly();
Schedule::job(new ImportRecentOwnMetricsJob)->dailyAt('04:00');

Schedule::job(new DispatchGuardianJob)->dailyAt('05:00');
Schedule::job(new EvaluateExpiredExperimentsJob)->dailyAt('05:30');

// Publishing is the one schedule tied to the user's calendar rather than to the night window.
Schedule::job(new DispatchDueContentJob)->everyFiveMinutes();

Schedule::job(new DispatchAudienceSnapshotsJob)->dailyAt('02:00');
