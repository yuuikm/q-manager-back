<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'question',
        'source',
        'status',
        'is_faq_added',
        'faq_id',
        'notes',
    ];

    protected $casts = [
        'is_faq_added' => 'boolean',
    ];

    /**
     * Related FAQ item if this lead question was added to FAQ
     */
    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class, 'faq_id');
    }
}
