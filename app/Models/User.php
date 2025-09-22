<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSubscriber(): bool
    {
        return $this->role === 'subscriber';
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    public function createToken(): string
    {
        $token = bin2hex(random_bytes(32));
        
        PersonalAccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->id,
            'name' => 'auth_token',
            'token' => hash('sha256', $token),
            'abilities' => ['*'],
            'last_used_at' => now(),
            'expires_at' => now()->addDays(7), // Reduced to 7 days for better security
        ]);

        return $token;
    }

    public function createRefreshToken(): string
    {
        $token = bin2hex(random_bytes(32));
        
        PersonalAccessToken::create([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->id,
            'name' => 'refresh_token',
            'token' => hash('sha256', $token),
            'abilities' => ['refresh'],
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30), // Refresh tokens last longer
        ]);

        return $token;
    }

    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    public function currentAccessToken()
    {
        return (object) [
            'id' => 1,
            'name' => 'auth_token',
            'abilities' => ['*'],
        ];
    }
}
