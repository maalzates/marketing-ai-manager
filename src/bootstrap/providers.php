<?php

use App\Modules\Accounts\AccountsServiceProvider;
use App\Modules\Admin\AdminServiceProvider;
use App\Modules\Ai\AiServiceProvider;
use App\Modules\Assets\AssetsServiceProvider;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Auth\AuthServiceProvider as ModuleAuthServiceProvider;
use App\Modules\Brands\BrandsServiceProvider;
use App\Modules\Campaigns\CampaignsServiceProvider;
use App\Modules\Chat\ChatServiceProvider;
use App\Modules\Competitors\CompetitorsServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Experiments\ExperimentsServiceProvider;
use App\Modules\Integrations\IntegrationsServiceProvider;
use App\Modules\Knowledge\KnowledgeServiceProvider;
use App\Modules\Onboarding\OnboardingServiceProvider;
use App\Modules\Proposals\ProposalsServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use App\Modules\Settings\SettingsServiceProvider;
use App\Modules\Strategies\StrategiesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // One entry per module. Keep alphabetical; Core first because everything depends on it.
    CoreServiceProvider::class,
    ReportingServiceProvider::class,
    OnboardingServiceProvider::class,
    ContentServiceProvider::class,
    CompetitorsServiceProvider::class,
    ChatServiceProvider::class,
    CampaignsServiceProvider::class,
    AssetsServiceProvider::class,
    AdminServiceProvider::class,
    AccountsServiceProvider::class,
    AiServiceProvider::class,
    AuditServiceProvider::class,
    ModuleAuthServiceProvider::class,
    BrandsServiceProvider::class,
    ExperimentsServiceProvider::class,
    IntegrationsServiceProvider::class,
    KnowledgeServiceProvider::class,
    ProposalsServiceProvider::class,
    SettingsServiceProvider::class,
    StrategiesServiceProvider::class,
];
