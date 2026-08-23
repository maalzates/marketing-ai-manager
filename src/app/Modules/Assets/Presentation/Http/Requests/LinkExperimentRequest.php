<?php

declare(strict_types=1);

namespace App\Modules\Assets\Presentation\Http\Requests;

use App\Modules\Assets\Application\DTO\AttachToExperimentDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class LinkExperimentRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'experiment_id' => ['required', 'integer', 'min:1'],
            'topic' => ['nullable', 'string', 'max:191'],
            'version' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['version' => $this->input('version', 1)]);
    }

    public function toDTO(): AttachToExperimentDTO
    {
        return new AttachToExperimentDTO(
            $this->container->make(AccountContext::class)->accountId,
            (int) $this->route('id'),
            $this->getIntegerValue('experiment_id'),
            $this->getStringValue('topic'),
            $this->getIntegerValue('version', 1),
        );
    }
}
