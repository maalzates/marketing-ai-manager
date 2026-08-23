<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Http\Requests;

use App\Modules\Assets\Application\DTO\UploadAssetDTO;
use App\Modules\Assets\Application\DTO\UploadedSourceDTO;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAssetRequest extends FormRequest
{
    use RequestHelperTrait;

    /** Instagram rejects reels above 300 MB, so nothing larger is worth streaming to Drive. */
    private const int MAX_KILOBYTES = 300 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
            'type' => ['required', new Enum(AssetType::class)],
            'strategy_id' => ['nullable', 'integer', 'min:1', 'required_without:experiment_id'],
            'experiment_id' => ['nullable', 'integer', 'min:1'],
            'topic' => ['nullable', 'string', 'max:191'],
            'version' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['version' => $this->input('version', 1)]);
    }

    public function toDTO(): UploadAssetDTO
    {
        return new UploadAssetDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getEnumValue('type', AssetType::class),
            new UploadedSourceDTO(
                (string) $this->file('file')->getRealPath(),
                $this->file('file')->getClientOriginalName(),
                (string) $this->file('file')->getMimeType(),
                (int) $this->file('file')->getSize(),
            ),
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('experiment_id'),
            $this->getStringValue('topic'),
            $this->getIntegerValue('version', 1),
        );
    }
}
