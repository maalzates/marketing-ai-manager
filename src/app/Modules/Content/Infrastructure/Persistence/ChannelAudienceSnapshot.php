<?php

declare(strict_types=1);

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Experiments\Domain\Enums\ExperimentPlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelAudienceSnapshot extends Model
{
    protected $fillable = [
        'account_id',
        'platform',
        'date',
        'followers_count',
        'follows_count',
        'media_count',
        'raw',
    ];

    protected $attributes = ['raw' => '[]'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'platform' => ExperimentPlatform::class,
            'date' => 'immutable_date',
            'followers_count' => 'integer',
            'follows_count' => 'integer',
            'media_count' => 'integer',
            'raw' => 'array',
        ];
    }
}
