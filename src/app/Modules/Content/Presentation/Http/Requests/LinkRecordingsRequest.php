<?php

declare(strict_types=1);

namespace App\Modules\Content\Presentation\Http\Requests;

use App\Modules\Content\Application\DTO\RecordingBatchDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class LinkRecordingsRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recordings' => ['required', 'array', 'min:1'],
            'recordings.*.script_id' => ['required', 'integer', 'min:1'],
            'recordings.*.asset_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): RecordingBatchDTO
    {
        return new RecordingBatchDTO(
            $this->container->make(AccountContext::class)->accountId,
            collect($this->getArrayValue('recordings'))
                ->mapWithKeys(fn (array $recording): array => [
                    (int) $recording['script_id'] => (int) $recording['asset_id'],
                ])
                ->all(),
        );
    }
}
