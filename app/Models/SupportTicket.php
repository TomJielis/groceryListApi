<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const OPEN = 'open';
    public const ANSWERED = 'answered';
    public const CLOSED = 'closed';

    protected $fillable = ['user_id', 'name', 'email', 'subject', 'status', 'unread_by_user', 'language', 'last_message_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
            'unread_by_user' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            $ticket->last_message_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Adds a message and moves the ticket to the status that follows from it: a message of the person who asked
     * (re)opens the ticket, an answer of the admin marks it answered and unread for the person who asked
     */
    public function addMessage(string $body, string $via, ?User $admin = null): SupportMessage
    {
        $message = $this->messages()->create([
            'body' => $body,
            'via' => $via,
            'from_admin' => $admin !== null,
            'admin_id' => $admin?->id,
        ]);

        $this->update([
            'status' => $admin ? self::ANSWERED : self::OPEN,
            'unread_by_user' => $admin !== null,
            'closed_at' => null,
            'last_message_at' => now(),
        ]);

        return $message;
    }

}
