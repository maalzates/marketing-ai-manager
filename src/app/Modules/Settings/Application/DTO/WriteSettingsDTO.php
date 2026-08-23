<?php

declare(strict_types=1);

namespace App\Modules\Settings\Application\DTO;

readonly class WriteSettingsDTO
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public int $accountId,
        public ?int $strategyId,
        public array $values,
    ) {}
}
