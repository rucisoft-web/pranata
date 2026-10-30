<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
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
