<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use Illuminate\Foundation\Http\FormRequest;

class ProposalScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
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
}
