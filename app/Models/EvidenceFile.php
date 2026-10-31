<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvidenceFile extends Model
{
    use \App\Models\Concerns\IntegerKeys;

    protected array $integerKeys = ['entity_id', 'subject_id', 'size', 'uploaded_by'];
    protected $guarded = ['id'];
    protected $hidden = ['path'];
}
