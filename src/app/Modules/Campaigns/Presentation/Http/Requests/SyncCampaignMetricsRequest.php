<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Http\Requests;

use App\Modules\Campaigns\Application\DTO\SyncCampaignMetricsDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class SyncCampaignMetricsRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:since'],
        ];
    }

    public function toDTO(): SyncCampaignMetricsDTO
    {
        return new SyncCampaignMetricsDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('experiment'),
            $this->date('since')?->toImmutable(),
            $this->date('until')?->toImmutable(),
        );
    }
}
