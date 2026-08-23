<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTO;

readonly class CreateCarouselDTO
{
    /**
     * @param  list<UploadedSourceDTO>  $slides  in publishing order; the index becomes `position`
     */
    public function __construct(
        public int $accountId,
        public string $topic,
        public array $slides,
        public ?int $strategyId = null,
        public ?int $experimentId = null,
    ) {}
}
