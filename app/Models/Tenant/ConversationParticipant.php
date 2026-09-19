<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'last_read_at', 'last_read_message_id', 'is_muted',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at'         => 'datetime',
            'last_read_message_id' => 'integer',
            'is_muted'             => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
