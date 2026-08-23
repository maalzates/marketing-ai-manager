<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The door for the read endpoints that carry no input beyond the route id: it still has
 * to state where the account comes from, and that is what a FormRequest is for here.
 */
class ExperimentScopeRequest extends FormRequest
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

    public function experimentId(): int
    {
        return (int) $this->route('id');
    }
}
