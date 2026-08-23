<?php

declare(strict_types=1);

namespace App\Modules\Chat\Presentation\Http\Requests;

use App\Modules\Chat\Application\DTO\StartConversationDTO;
use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDTO(): StartConversationDTO
    {
        return new StartConversationDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->container->make(AccountContext::class)->userId,
            $this->getStringValue('title'),
        );
    }
}
