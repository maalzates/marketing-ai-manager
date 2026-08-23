<?php

declare(strict_types=1);

namespace App\Modules\Settings\Infrastructure\Persistence;

use App\Modules\Settings\Domain\Enums\SettingScope;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected $fillable = [
        'scope',
        'scope_id',
        'key',
        'value',
    ];

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    protected function casts(): array
    {
        return [
            'scope' => SettingScope::class,
            'scope_id' => 'integer',
            'value' => 'json',
        ];
    }
}
