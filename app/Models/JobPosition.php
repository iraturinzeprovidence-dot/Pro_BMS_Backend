<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department',
        'description',
        'requirements',
        'type',
        'status',
        'salary_min',
        'salary_max',
        'deadline',
    ];

    public function candidates()
    {
        return $this->hasMany(Candidate::class, 'job_position_id');
    }
}