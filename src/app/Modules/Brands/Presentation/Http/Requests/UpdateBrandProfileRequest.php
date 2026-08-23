<?php

declare(strict_types=1);

namespace App\Modules\Brands\Presentation\Http\Requests;

use App\Modules\Brands\Application\DTO\UpdateBrandProfileDTO;
use App\Modules\Brands\Domain\Enums\BrandKind;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateBrandProfileRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', new Enum(BrandKind::class)],
            'description' => ['nullable', 'string'],
            'niche' => ['nullable', 'string', 'max:255'],
            'value_proposition' => ['nullable', 'string'],
            'tone_of_voice' => ['nullable', 'string'],
            'values' => ['nullable', 'array'],
            'values.*' => ['string', 'max:255'],
            'banned_topics' => ['nullable', 'array'],
            'banned_topics.*' => ['string', 'max:255'],
            'buyer_personas' => ['nullable', 'array'],
            'reference_competitors' => ['nullable', 'array'],
            'reference_competitors.*' => ['string', 'max:255'],
            'brand_colors' => ['nullable', 'array'],
            'brand_colors.*' => ['string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }

    public function toDTO(): UpdateBrandProfileDTO
    {
        return new UpdateBrandProfileDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('id'),
            $this->getStringValue('name'),
            $this->getEnumValue('kind', BrandKind::class),
            $this->getStringValue('description'),
            $this->getStringValue('niche'),
            $this->getStringValue('value_proposition'),
            $this->getStringValue('tone_of_voice'),
            $this->validated('values'),
            $this->validated('banned_topics'),
            $this->validated('buyer_personas'),
            $this->validated('reference_competitors'),
            $this->validated('brand_colors'),
        );
    }
}
