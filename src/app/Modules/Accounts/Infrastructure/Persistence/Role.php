<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Infrastructure\Persistence;

use App\Models\User;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = ['name', 'label'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }
}
