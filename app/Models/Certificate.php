<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_number',
        'course_id',
        'user_id',
        'enrollment_id',
        'pdf_path',
        'final_score',
        'issued_at',
        'is_valid',
    ];

    protected $casts = [
        'final_score' => 'decimal:2',
        'issued_at' => 'datetime',
        'is_valid' => 'boolean',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(CourseEnrollment::class);
    }
}
