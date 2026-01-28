<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class DocumentSubcategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category_id',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'subcategory_id');
    }

    // Auto-generate slug from name
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($subcategory) {
            if (empty($subcategory->slug)) {
                $subcategory->slug = Str::slug($subcategory->name);
            }
        });
    }
}
