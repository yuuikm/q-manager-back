<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'content',
        'price',
        'type',
        'featured_image',
        'certificate_template',
        'max_students',
        'current_students',
        'duration_hours',
        'requirements',
        'learning_outcomes',
        'zoom_link',
        'schedule',
        'is_published',
        'is_featured',
        'views_count',
        'enrollments_count',
        'completion_rate',
        'created_by',
        'category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'max_students' => 'integer',
        'current_students' => 'integer',
        'duration_hours' => 'integer',
        'views_count' => 'integer',
        'enrollments_count' => 'integer',
        'completion_rate' => 'integer',
        'schedule' => 'array',
    ];

    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(CourseCategory::class);
    }

    public function materials()
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function tests()
    {
        return $this->hasMany(Test::class);
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->whereHas('category', function ($q) use ($category) {
            $q->where('name', $category);
        });
    }
}
