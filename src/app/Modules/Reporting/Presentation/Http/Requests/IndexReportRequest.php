<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Presentation\Http\Requests;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Requests\RequestHelperTrait;
use App\Modules\Reporting\Application\DTO\ReportFilterDTO;
use App\Modules\Reporting\Domain\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class IndexReportRequest extends FormRequest
{
    use RequestHelperTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy_id' => ['nullable', 'integer', 'min:1'],
            'experiment_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', new Enum(ReportType::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toDTO(): ReportFilterDTO
    {
        return new ReportFilterDTO(
            $this->container->make(AccountContext::class)->accountId,
            $this->getIntegerValue('strategy_id'),
            $this->getIntegerValue('experiment_id'),
            $this->getEnumValue('type', ReportType::class),
            $this->getIntegerValue('per_page', 0),
            $this->getIntegerValue('page', 1),
        );
    }
}
