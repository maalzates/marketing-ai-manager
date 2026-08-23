<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Http\Requests;

use App\Modules\Assets\Application\DTO\LinkExistingAssetDTO;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class LinkExistingAssetRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'drive_file_id' => ['required', 'string', 'max:191'],
            'type' => ['required', new Enum(AssetType::class)],
            'strategy_id' => ['nullable', 'integer', 'min:1', 'required_without:experiment_id'],
            'experiment_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): LinkExistingAssetDTO
    {
        return new LinkExistingAssetDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getStringValue('drive_file_id'),
            $this->getEnumValue('type', AssetType::class),
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('experiment_id'),
        );
    }
}
