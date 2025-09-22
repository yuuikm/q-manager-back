<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    // Relationships
    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_categories');
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_categories');
    }

    public function news()
    {
        return $this->belongsToMany(News::class, 'news_categories');
    }

    // Auto-generate slug from name
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = \Str::slug($category->name);
            }
        });
    }
}
