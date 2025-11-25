<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TestQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'question',
        'type',
        'options',
        'correct_answer',
        'points',
        'explanation',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'points' => 'integer',
        'order' => 'integer',
    ];

    // Relationships
    public function test()
    {
        return $this->belongsTo(Test::class);
    }
}
