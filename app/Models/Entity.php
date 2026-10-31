<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['parent_id'];
    protected $fillable = ['code', 'name', 'parent_id', 'is_bumn', 'is_public_company', 'is_listed', 'is_demo'];

    protected function casts(): array
    {
        return ['is_bumn' => 'boolean', 'is_public_company' => 'boolean', 'is_listed' => 'boolean', 'is_demo' => 'boolean'];
    }
}
