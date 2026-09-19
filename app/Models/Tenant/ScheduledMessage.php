<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledMessage extends Model
{
    protected $fillable = [
        'user_id', 'channel_id', 'conversation_id', 'body', 'type',
        'files', 'mentions', 'metadata', 'scheduled_for', 'status',
        'sent_message_id',
    ];

    protected function casts(): array
    {
        return [
            'files'         => 'array',
            'mentions'      => 'array',
            'metadata'      => 'array',
            'scheduled_for' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sentMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'sent_message_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDueBy($query, \DateTimeInterface $time)
    {
        return $query->where('status', 'pending')->where('scheduled_for', '<=', $time);
    }

    public function toPendingArray(): array
    {
        return [
            'id'              => $this->id,
            'channel_id'      => $this->channel_id,
            'conversation_id' => $this->conversation_id,
            'channel_name'    => $this->channel?->name,
            'body'            => $this->body,
            'type'            => $this->type,
            'scheduled_for'   => $this->scheduled_for?->toIso8601String(),
            'status'          => $this->status,
        ];
    }
}
