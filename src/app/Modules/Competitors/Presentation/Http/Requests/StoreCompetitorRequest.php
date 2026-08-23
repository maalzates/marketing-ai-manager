<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Presentation\Http\Requests;

use App\Modules\Competitors\Application\DTO\CreateCompetitorDTO;
use App\Modules\Competitors\Domain\Enums\CompetitorPlatform;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

class StoreCompetitorRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', new Enum(CompetitorPlatform::class)],
            'handle' => ['required', 'string', 'max:191'],
            'strategy_id' => ['nullable', 'integer', 'min:1'],
            'display_name' => ['nullable', 'string', 'max:191'],
            'external_id' => ['nullable', 'string', 'max:191'],
        ];
    }

    /** "@natgeo", "natgeo/" and "NatGeo" are the same competitor and must collide on the unique key. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'handle' => Str::lower(trim((string) $this->input('handle'), " \t\n\r\0\x0B@/")),
        ]);
    }

    public function toDTO(): CreateCompetitorDTO
    {
        return new CreateCompetitorDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getEnumValue('platform', CompetitorPlatform::class),
            $this->getStringValue('handle'),
            $this->getIntegerValue('strategy_id'),
            $this->getStringValue('display_name'),
            $this->getStringValue('external_id'),
        );
    }
}
