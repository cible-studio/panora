<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClientUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'name', 'email', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
        'password'      => 'hashed',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
