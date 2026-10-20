<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faq extends Model
{
    use HasFactory;

    protected $table = 'faqs';

    protected $fillable = [
        'parent_id',
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Parent question
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'parent_id');
    }

    /**
     * Immediate children questions
     */
    public function children(): HasMany
    {
        return $this->hasMany(Faq::class, 'parent_id')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Immediate active children questions
     */
    public function activeChildren(): HasMany
    {
        return $this->hasMany(Faq::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Recursive active children for tree building
     */
    public function allActiveChildren(): HasMany
    {
        return $this->activeChildren()->with('allActiveChildren');
    }

    /**
     * Recursive all children for admin tree
     */
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Get all descendant IDs of this node to prevent circular references
     */
    public function getAllDescendantIds(): array
    {
        $ids = [];
        $children = $this->children;

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }
}
