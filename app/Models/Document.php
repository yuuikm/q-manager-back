<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'title',
        'description',
        'price',
        'preview_pages',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'preview_file_path',
        'preview_file_name',
        'preview_file_size',
        'is_active',
        'created_by',
        'user_id',
        'buy_number',
        'category_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'preview_pages' => 'integer',
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'preview_file_size' => 'integer',
        'buy_number' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->whereHas('category', function ($q) use ($category) {
            $q->where('name', $category);
        });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function purchases()
    {
        return $this->hasMany(DocumentPurchase::class);
    }

    public function isPurchasedBy($userId)
    {
        return $this->purchases()
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->exists();
    }
}
