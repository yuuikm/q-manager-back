<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ManagerHelp extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'description',
        'file_path',
        'file_name',
        'youtube_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ManagerHelpCategory::class, 'category_id');
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($managerHelp) {
            if (empty($managerHelp->slug)) {
                $managerHelp->slug = Str::slug($managerHelp->title);
            }
        });
    }
}
