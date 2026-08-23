<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Controllers\Api;

use App\Modules\Content\Application\DTO\CalendarQueryDTO;
use App\Modules\Content\Application\Services\PublishingService;
use App\Modules\Content\Application\Services\SlotSuggestionService;
use App\Modules\Content\Presentation\Http\Requests\CalendarRequest;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class ContentCalendarController extends ApiController
{
    public function __construct(
        private readonly PublishingService $service,
        private readonly SlotSuggestionService $slots,
    ) {
        parent::__construct();
    }

    public function __invoke(CalendarRequest $request): JsonResponse
    {
        $query = $request->toDTO();

        return $this->response->success([
            'schedules' => $this->service->calendar($query),
            'suggested_slots' => $this->suggestions($request->integer('suggest'), $query),
        ]);
    }

    /**
     * Suggestions are only computed when asked for and only for one strategy: the ranking reads
     * that strategy's cadence and the account's own history.
     */
    private function suggestions(int $count, CalendarQueryDTO $query): array
    {
        return $count > 0 && $query->strategyId !== null
            ? $this->slots->suggest($query->accountId, $query->strategyId, $count, $query->from)->all()
            : [];
    }
}
