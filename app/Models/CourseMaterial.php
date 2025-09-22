<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CourseMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'course_id',
        'type',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'external_url',
        'content',
        'duration_minutes',
        'sort_order',
        'is_required',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration_minutes' => 'integer',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
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
}
