<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Experiments\Domain\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmVerdictRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verdict' => ['required', Rule::enum(Verdict::class)],
            'reason' => ['required', 'string', 'max:2000'],
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

    public function experimentId(): int
    {
        return (int) $this->route('id');
    }

    public function verdict(): Verdict
    {
        return $this->getEnumValue('verdict', Verdict::class);
    }

    public function reason(): string
    {
        return $this->getStringValue('reason');
    }
}
