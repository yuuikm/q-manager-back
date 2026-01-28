<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    // Document type constants
    public const TYPE_DOCUMENTED_PROCEDURES = 'Документированные процедуры';
    public const TYPE_MAIN_PROCESS_MAPS = 'Карты основных процессов';
    public const TYPE_SUPPORTING_PROCESS_MAPS = 'Карты поддерживающих процессов';
    public const TYPE_MANAGEMENT_PROCESS_MAPS = 'Карты управляющих процессов';
    public const TYPE_QUALITY_MANUAL = 'Руководство по качеству';
    public const TYPE_PRODUCTION_INSTRUCTIONS = 'Производственные инструкции';
    public const TYPE_GMP_MANUAL = 'Руководство по надлежащей производственной практике';

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
        'subcategory_id',
        'document_type',
    ];

    protected $casts = [
        'price' => 'integer',
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

    public function subcategory()
    {
        return $this->belongsTo(DocumentSubcategory::class);
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
