<?php

declare(strict_types=1);

namespace App\Modules\Settings\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Settings\Application\DTO\SettingScopeFilterDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Serves both /settings and /settings/strategies/{strategy}: the route parameter is the
 * only thing that tells the two apart.
 */
class IndexSettingsRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['strategy' => $this->route('strategy')]);
    }

    public function toDTO(): SettingScopeFilterDTO
    {
        return new SettingScopeFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('strategy'),
        );
    }
}
