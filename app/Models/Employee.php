<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department',
        'job_title',
        'salary',
        'hire_date',
        'status',
        'address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}