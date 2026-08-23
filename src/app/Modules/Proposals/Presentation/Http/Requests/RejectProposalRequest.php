<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class RejectProposalRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function accountId(): int
    {
        return $this->container->make(AccountContext::class)->accountId;
    }

    public function userId(): int
    {
        return $this->container->make(AccountContext::class)->userId;
    }

    public function proposalId(): int
    {
        return (int) $this->route('id');
    }

    public function reason(): ?string
    {
        return $this->getStringValue('reason');
    }
}
