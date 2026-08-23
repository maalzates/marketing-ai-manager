<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\ValueObjects;

use App\Modules\Reporting\Domain\Enums\AnomalyKind;

/**
 * One arithmetic fact about one experiment. The summary is written here, deterministically,
 * so a finding is complete without a model: the LLM only ever rephrases it.
 */
readonly class AnomalyFinding
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public AnomalyKind $kind,
        public int $experimentId,
        public string $experimentCode,
        public string $summary,
        public array $evidence,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'experiment_id' => $this->experimentId,
            'experiment_code' => $this->experimentCode,
            'summary' => $this->summary,
            'evidence' => $this->evidence,
        ];
    }
}
