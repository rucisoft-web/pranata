<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit event tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit event tidak dapat dihapus.'));
    }
}
