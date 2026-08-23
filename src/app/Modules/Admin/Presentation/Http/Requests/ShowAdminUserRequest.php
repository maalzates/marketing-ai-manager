<?php

declare(strict_types=1);

namespace App\Modules\Admin\Presentation\Http\Requests;

use App\Modules\Admin\Application\DTO\AdminUserDetailDTO;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ShowAdminUserRequest extends FormRequest
{
    use RequestHelperTrait;

    private const int DEFAULT_WINDOW_DAYS = 30;

    private const int DEFAULT_ACTION_LOG_PER_PAGE = 25;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'action_log_per_page' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from', now()->subDays(self::DEFAULT_WINDOW_DAYS)->toDateString()),
            'to' => $this->input('to', now()->toDateString()),
            'action_log_per_page' => $this->input('action_log_per_page', self::DEFAULT_ACTION_LOG_PER_PAGE),
        ]);
    }

    public function toDTO(): AdminUserDetailDTO
    {
        return new AdminUserDetailDTO(
            (int) $this->route('id'),
            CarbonImmutable::parse($this->getStringValue('from'))->startOfDay(),
            CarbonImmutable::parse($this->getStringValue('to'))->endOfDay(),
            $this->getIntegerValue('action_log_per_page', self::DEFAULT_ACTION_LOG_PER_PAGE),
        );
    }
}
