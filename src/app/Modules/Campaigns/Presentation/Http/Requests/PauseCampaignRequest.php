<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Presentation\Http\Requests;

use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Campaigns\Application\DTO\PauseCampaignDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class PauseCampaignRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toDTO(): PauseCampaignDTO
    {
        return new PauseCampaignDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('experiment'),
            $this->getStringValue('reason'),
            $this->container->make(AccountContext::class)->userId,
            ActionOrigin::UI,
        );
    }
}
