<?php

declare(strict_types=1);

namespace App\Modules\Brands\Infrastructure\Persistence;

use App\Modules\Brands\Domain\Enums\BrandKind;
use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'kind',
        'description',
        'niche',
        'value_proposition',
        'tone_of_voice',
        'values',
        'banned_topics',
        'buyer_personas',
        'reference_competitors',
        'brand_colors',
    ];

    /** The json columns are not nullable, so every insert needs a shape even when empty. */
    protected $attributes = [
        'values' => '[]',
        'banned_topics' => '[]',
        'buyer_personas' => '[]',
        'reference_competitors' => '[]',
        'brand_colors' => '[]',
    ];

    protected static function newFactory(): Factory
    {
        return BrandProfileFactory::new();
    }

    protected function casts(): array
    {
        return [
            'kind' => BrandKind::class,
            'values' => 'array',
            'banned_topics' => 'array',
            'buyer_personas' => 'array',
            'reference_competitors' => 'array',
            'brand_colors' => 'array',
        ];
    }
}
