<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTO;

readonly class GoogleProfileDTO
{
    public function __construct(
        public string $googleId,
        public string $email,
        public string $name,
        public ?string $avatarUrl,
    ) {}
}
