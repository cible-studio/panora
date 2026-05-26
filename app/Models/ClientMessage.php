<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id', 'from_name', 'from_email', 'subject', 'body',
        'status', 'read_at', 'read_by',
        'reply_body', 'replied_at', 'replied_by',
    ];

    protected $casts = [
        'read_at'    => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    /**
     * Helpers de statut — évitent de chasser les littéraux 'new' dans la vue.
     */
    public function isNew(): bool       { return $this->status === 'new'; }
    public function isInProgress(): bool { return $this->status === 'in_progress'; }
    public function isReplied(): bool   { return $this->status === 'replied'; }
    public function isArchived(): bool  { return $this->status === 'archived'; }
}
