<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['user_id'];
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
}
