<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RegulatorySource extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'created_by'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['effective_date' => 'date']; }
}
