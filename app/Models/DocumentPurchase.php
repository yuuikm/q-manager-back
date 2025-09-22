<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPurchase extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'company',
        'notes',
        'price_paid',
        'status',
        'purchased_at',
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'purchased_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}