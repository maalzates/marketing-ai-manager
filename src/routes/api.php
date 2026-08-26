<?php

declare(strict_types=1);

use App\Modules\Admin\Presentation\Http\Controllers\Api\AdminActionLogController;
use App\Modules\Admin\Presentation\Http\Controllers\Api\AdminApiKeyController;
use App\Modules\Admin\Presentation\Http\Controllers\Api\AdminRoleController;
use App\Modules\Admin\Presentation\Http\Controllers\Api\AdminSettingController;
use App\Modules\Admin\Presentation\Http\Controllers\Api\AdminUsageController;
use App\Modules\Admin\Presentation\Http\Controllers\Api\AdminUserController;
use App\Modules\Ai\Presentation\Http\Controllers\Api\AiSuggestionController;
use App\Modules\Ai\Presentation\Http\Controllers\Api\ModelCatalogController;
use App\Modules\Assets\Presentation\Http\Controllers\Api\AssetController;
use App\Modules\Audit\Presentation\Http\Controllers\Api\ActionLogController;
use App\Modules\Audit\Presentation\Http\Controllers\Api\UsageController;
use App\Modules\Auth\Presentation\Http\Controllers\Api\AuthController;
use App\Modules\Brands\Presentation\Http\Controllers\Api\BrandProfileController;
use App\Modules\Campaigns\Presentation\Http\Controllers\Api\CampaignController;
use App\Modules\Chat\Presentation\Http\Controllers\Api\ChatController;
use App\Modules\Chat\Presentation\Http\Controllers\Api\ChatConversationController;
use App\Modules\Competitors\Presentation\Http\Controllers\Api\CompetitorController;
use App\Modules\Competitors\Presentation\Http\Controllers\Api\InsightController;
use App\Modules\Content\Presentation\Http\Controllers\Api\ContentCalendarController;
use App\Modules\Content\Presentation\Http\Controllers\Api\ContentScheduleController;
use App\Modules\Content\Presentation\Http\Controllers\Api\ContentScriptController;
use App\Modules\Core\Presentation\Http\Controllers\Api\HealthController;
use App\Modules\Experiments\Presentation\Http\Controllers\Api\ExperimentController;
use App\Modules\Experiments\Presentation\Http\Controllers\Api\ExperimentMetricController;
use App\Modules\Experiments\Presentation\Http\Controllers\Api\ExperimentVerdictController;
use App\Modules\Experiments\Presentation\Http\Controllers\Api\ExperimentWarningController;
use App\Modules\Integrations\Presentation\Http\Controllers\Api\IntegrationController;
use App\Modules\Integrations\Presentation\Http\Controllers\Api\IntegrationOAuthController;
use App\Modules\Knowledge\Presentation\Http\Controllers\Api\AdminKnowledgeController;
use App\Modules\Knowledge\Presentation\Http\Controllers\Api\KnowledgeController;
use App\Modules\Onboarding\Presentation\Http\Controllers\Api\OnboardingController;
use App\Modules\Proposals\Presentation\Http\Controllers\Api\AcceptProposalController;
use App\Modules\Proposals\Presentation\Http\Controllers\Api\ProposalController;
use App\Modules\Reporting\Presentation\Http\Controllers\Api\GuardianRunController;
use App\Modules\Reporting\Presentation\Http\Controllers\Api\ReportController;
use App\Modules\Settings\Presentation\Http\Controllers\Api\SettingController;
use App\Modules\Strategies\Presentation\Http\Controllers\Api\StrategyController;
use Illuminate\Support\Facades\Route;

// Unversioned on purpose: an infrastructure liveness probe must not move with the API.
Route::get('/health', [HealthController::class, 'show']);

Route::prefix('v1')->group(function (): void {
    // --- Auth ---------------------------------------------------------------
    Route::prefix('auth')->group(function (): void {
        Route::get('google/redirect', [AuthController::class, 'redirect']);
        Route::get('google/callback', [AuthController::class, 'callback']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });
    });

    // --- Onboarding ---------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/onboarding', [OnboardingController::class, 'show']);
        Route::get('/onboarding/checklist', [OnboardingController::class, 'checklist']);
        Route::post('/onboarding/steps/{step}/complete', [OnboardingController::class, 'complete']);
        Route::post('/onboarding/steps/{step}/skip', [OnboardingController::class, 'skip']);
    });

    // --- Integrations -------------------------------------------------------
    // The OAuth callback is public: the provider redirects the browser here with no bearer
    // token. The signed, single-use `state` is what ties the request back to an account.
    Route::get('/integrations/{provider}/oauth/callback', [IntegrationOAuthController::class, 'callback'])
        ->name('integrations.oauth.callback');

    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/integrations', [IntegrationController::class, 'index']);
        Route::put('/integrations/{provider}', [IntegrationController::class, 'update']);
        Route::delete('/integrations/{provider}', [IntegrationController::class, 'destroy']);
        Route::post('/integrations/{provider}/verify', [IntegrationController::class, 'verify']);
        Route::get('/integrations/{provider}/oauth/redirect', [IntegrationOAuthController::class, 'redirect']);
    });

    // --- Settings -----------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::get('/settings/strategies/{strategy}', [SettingController::class, 'index']);
        Route::put('/settings/strategies/{strategy}', [SettingController::class, 'update']);
    });

    // --- Audit --------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/usage', [UsageController::class, 'index']);
        Route::get('/action-logs', [ActionLogController::class, 'index']);
    });

    // --- Knowledge ----------------------------------------------------------
    // Platform content, shared by every account: authenticated but not account-scoped.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/knowledge/{type}', [KnowledgeController::class, 'index']);
        Route::get('/knowledge/{type}/{key}', [KnowledgeController::class, 'show']);
    });

    // --- Brands -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/brand-profiles', [BrandProfileController::class, 'index']);
        Route::post('/brand-profiles', [BrandProfileController::class, 'store']);
        Route::get('/brand-profiles/{id}', [BrandProfileController::class, 'show'])->whereNumber('id');
        Route::put('/brand-profiles/{id}', [BrandProfileController::class, 'update'])->whereNumber('id');
        Route::delete('/brand-profiles/{id}', [BrandProfileController::class, 'destroy'])->whereNumber('id');
    });

    // --- Strategies ---------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/strategies', [StrategyController::class, 'index']);
        Route::post('/strategies', [StrategyController::class, 'store']);
        Route::get('/strategies/{id}', [StrategyController::class, 'show'])->whereNumber('id');
        Route::put('/strategies/{id}', [StrategyController::class, 'update'])->whereNumber('id');
        Route::delete('/strategies/{id}', [StrategyController::class, 'destroy'])->whereNumber('id');
        Route::post('/strategies/{id}/activate', [StrategyController::class, 'activate'])->whereNumber('id');
        Route::post('/strategies/{id}/pause', [StrategyController::class, 'pause'])->whereNumber('id');
        Route::post('/strategies/{id}/archive', [StrategyController::class, 'archive'])->whereNumber('id');
    });

    // --- Experiments --------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/strategies/{strategy}/experiments', [ExperimentController::class, 'index'])->whereNumber('strategy');
        Route::post('/strategies/{strategy}/experiments', [ExperimentController::class, 'store'])->whereNumber('strategy');
        Route::get('/experiments/{id}', [ExperimentController::class, 'show'])->whereNumber('id');
        Route::put('/experiments/{id}', [ExperimentController::class, 'update'])->whereNumber('id');
        Route::get('/experiments/{id}/metrics', [ExperimentMetricController::class, 'index'])->whereNumber('id');
        Route::get('/experiments/{id}/warnings', [ExperimentWarningController::class, 'index'])->whereNumber('id');
        Route::post('/experiments/{id}/verdict', [ExperimentVerdictController::class, 'store'])->whereNumber('id');
    });

    // --- Proposals ----------------------------------------------------------
    // `accept` is the one door that executes a mutation, and it has its own controller so
    // the container can hand ProposalExecutionService to it and to nothing else.
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/proposals', [ProposalController::class, 'index']);
        Route::get('/proposals/{id}', [ProposalController::class, 'show'])->whereNumber('id');
        Route::post('/proposals/{id}/accept', [AcceptProposalController::class, 'store'])->whereNumber('id');
        Route::post('/proposals/{id}/reject', [ProposalController::class, 'reject'])->whereNumber('id');
    });

    // --- Competitors --------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/competitors', [CompetitorController::class, 'index']);
        Route::post('/competitors', [CompetitorController::class, 'store']);
        Route::get('/competitors/{id}', [CompetitorController::class, 'show'])->whereNumber('id');
        Route::delete('/competitors/{id}', [CompetitorController::class, 'destroy'])->whereNumber('id');
        Route::post('/competitors/{id}/sync', [CompetitorController::class, 'sync'])->whereNumber('id');
        Route::get('/competitors/{id}/posts', [CompetitorController::class, 'posts'])->whereNumber('id');
        Route::get('/insights', [InsightController::class, 'index']);
        Route::post('/insights/{id}/discard', [InsightController::class, 'discard'])->whereNumber('id');
    });

    // --- Content ------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/content/scripts', [ContentScriptController::class, 'index']);
        Route::post('/content/scripts', [ContentScriptController::class, 'store']);
        Route::post('/content/scripts/generate', [ContentScriptController::class, 'generate']);
        Route::get('/content/scripts/{id}', [ContentScriptController::class, 'show'])->whereNumber('id');
        Route::put('/content/scripts/{id}', [ContentScriptController::class, 'update'])->whereNumber('id');
        Route::post('/content/scripts/{id}/approve', [ContentScriptController::class, 'approve'])->whereNumber('id');
        Route::get('/content/schedules', [ContentScheduleController::class, 'index']);
        Route::post('/content/schedules', [ContentScheduleController::class, 'store']);
        Route::post('/content/schedules/recordings', [ContentScheduleController::class, 'recordings']);
        Route::put('/content/schedules/{id}', [ContentScheduleController::class, 'update'])->whereNumber('id');
        Route::delete('/content/schedules/{id}', [ContentScheduleController::class, 'destroy'])->whereNumber('id');
        Route::get('/content/calendar', [ContentCalendarController::class, 'index']);
    });

    // --- Assets -------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/assets', [AssetController::class, 'index']);
        Route::post('/assets', [AssetController::class, 'store']);
        Route::post('/assets/link-existing', [AssetController::class, 'linkExisting']);
        Route::get('/assets/{id}', [AssetController::class, 'show'])->whereNumber('id');
        Route::delete('/assets/{id}', [AssetController::class, 'destroy'])->whereNumber('id');
        Route::post('/assets/{id}/link-experiment', [AssetController::class, 'linkExperiment'])->whereNumber('id');
        Route::post('/assets/{id}/ready', [AssetController::class, 'ready'])->whereNumber('id');
    });

    // --- Campaigns ----------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/campaigns/{experiment}', [CampaignController::class, 'show'])->whereNumber('experiment');
        Route::post('/campaigns/{experiment}/sync', [CampaignController::class, 'sync'])->whereNumber('experiment');
        Route::post('/campaigns/{experiment}/pause', [CampaignController::class, 'pause'])->whereNumber('experiment');
    });

    // --- Chat ---------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account', 'throttle:chat'])->group(function (): void {
        Route::post('/chat', [ChatController::class, 'store']);
        Route::get('/chat/conversations', [ChatConversationController::class, 'index']);
        Route::post('/chat/conversations', [ChatConversationController::class, 'store']);
        Route::get('/chat/conversations/{id}', [ChatConversationController::class, 'show'])->whereNumber('id');
        Route::delete('/chat/conversations/{id}', [ChatConversationController::class, 'destroy'])->whereNumber('id');
    });

    // --- Ai -----------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::post('/ai/suggest', [AiSuggestionController::class, 'store']);
        Route::post('/ai/models/refresh', [ModelCatalogController::class, 'store']);
    });

    // --- Reporting ----------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account'])->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/{id}', [ReportController::class, 'show'])->whereNumber('id');
        Route::post('/strategies/{strategy}/guardian/run', [GuardianRunController::class, 'store'])->whereNumber('strategy');
    });

    // --- Admin --------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'account', 'role:admin', 'throttle:admin'])->prefix('admin')->group(function (): void {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->whereNumber('id');
        Route::put('/users/{id}', [AdminUserController::class, 'update'])->whereNumber('id');
        Route::post('/users/{id}/roles', [AdminUserController::class, 'assignRole'])->whereNumber('id');
        Route::delete('/users/{id}/roles/{role}', [AdminUserController::class, 'removeRole'])->whereNumber('id');
        Route::get('/roles', [AdminRoleController::class, 'index']);
        Route::post('/roles', [AdminRoleController::class, 'store']);
        Route::put('/roles/{id}', [AdminRoleController::class, 'update'])->whereNumber('id');
        Route::delete('/roles/{id}', [AdminRoleController::class, 'destroy'])->whereNumber('id');
        Route::get('/api-keys', [AdminApiKeyController::class, 'index']);
        Route::post('/api-keys', [AdminApiKeyController::class, 'store']);
        Route::delete('/api-keys/{id}', [AdminApiKeyController::class, 'destroy'])->whereNumber('id');
        Route::get('/settings', [AdminSettingController::class, 'index']);
        Route::put('/settings', [AdminSettingController::class, 'update']);
        Route::get('/usage', [AdminUsageController::class, 'index']);
        Route::get('/action-logs', [AdminActionLogController::class, 'index']);
    });
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
        Route::get('/admin/knowledge', [AdminKnowledgeController::class, 'index']);
        Route::post('/admin/knowledge', [AdminKnowledgeController::class, 'store']);
        Route::put('/admin/knowledge/{id}', [AdminKnowledgeController::class, 'update'])->whereNumber('id');
        Route::delete('/admin/knowledge/{id}', [AdminKnowledgeController::class, 'destroy'])->whereNumber('id');
    });
});
