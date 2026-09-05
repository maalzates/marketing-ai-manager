<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Presentation\Http\Requests;

use App\Modules\Accounts\Application\DTO\UpdateAccountDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateAccountRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'alpha:ascii'],
        ];
    }

    public function toDTO(): UpdateAccountDTO
    {
        return new UpdateAccountDTO(
            $this->container->make(AccountContext::class)->accountId,
            Str::upper($this->getStringValue('currency')),
        );
    }
}
