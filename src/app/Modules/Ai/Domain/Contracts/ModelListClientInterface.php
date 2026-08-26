<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

use Illuminate\Support\Collection;

interface ModelListClientInterface
{
    /** @return Collection<int, string> the model ids this credential can call, every page of them */
    public function list(): Collection;
}
