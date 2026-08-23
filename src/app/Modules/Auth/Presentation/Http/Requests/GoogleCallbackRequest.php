<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Requests;

use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Illuminate\Foundation\Http\FormRequest;

class GoogleCallbackRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:2048'],
            'state' => ['required', 'string', 'max:2048'],
        ];
    }

    public function code(): string
    {
        return (string) $this->getStringValue('code');
    }

    public function state(): string
    {
        return (string) $this->getStringValue('state');
    }
}
