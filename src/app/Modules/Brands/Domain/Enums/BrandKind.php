<?php

declare(strict_types=1);

namespace App\Modules\Brands\Domain\Enums;

enum BrandKind: string
{
    case PersonalBrand = 'personal_brand';

    case Company = 'company';

    case Project = 'project';
}
