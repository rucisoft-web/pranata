<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_hashes', 'mfa_last_counter', 'mfa_epoch'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'mfa_secret' => 'encrypted', 'mfa_recovery_hashes' => 'array', 'mfa_confirmed_at' => 'datetime', 'mfa_last_counter' => 'integer', 'is_demo_guest' => 'boolean'];
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class)->withPivot('role')->withTimestamps();
    }

    public function roleIn(int $entityId): ?string
    {
        return $this->entities()->where('entities.id', $entityId)->first()?->pivot->role;
    }
}
