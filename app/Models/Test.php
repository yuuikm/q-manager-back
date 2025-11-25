<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Test extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'course_id',
        'time_limit_minutes',
        'passing_score',
        'max_attempts',
        'total_questions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'time_limit_minutes' => 'integer',
        'passing_score' => 'integer',
        'max_attempts' => 'integer',
        'total_questions' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions()
    {
        return $this->hasMany(TestQuestion::class)->orderBy('order');
    }
}
