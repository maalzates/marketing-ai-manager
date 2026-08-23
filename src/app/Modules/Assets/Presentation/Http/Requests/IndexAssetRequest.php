<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Http\Requests;

use App\Modules\Assets\Application\DTO\AssetFilterDTO;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexAssetRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy_id' => ['nullable', 'integer', 'min:1'],
            'experiment_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', new Enum(AssetType::class)],
            'status' => ['nullable', new Enum(AssetStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): AssetFilterDTO
    {
        return new AssetFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('experiment_id'),
            $this->getEnumValue('type', AssetType::class),
            $this->getEnumValue('status', AssetStatus::class),
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
