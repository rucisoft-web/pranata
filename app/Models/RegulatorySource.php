<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RegulatorySource extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['effective_date' => 'date']; }
}
