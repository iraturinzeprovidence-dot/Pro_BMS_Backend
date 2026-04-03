<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_position_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cover_letter',
        'cv_path',
        'status',
    ];

    public function jobPosition()
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }
}