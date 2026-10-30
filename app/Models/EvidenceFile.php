<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvidenceFile extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['path'];
}
