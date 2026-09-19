<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Denormalised summary of a message's replies.
 *
 * The replies themselves are ordinary messages linked by `parent_id`; this row
 * exists so a channel listing can show "3 replies · last reply 2m ago" without
 * aggregating the messages table on every render.
 */
class MessageThread extends Model
{
    protected $fillable = [
        'parent_message_id',
        'channel_id',
        'replies_count',
        'last_reply_id',
        'last_reply_at',
        'participant_ids',
    ];

    protected function casts(): array
    {
        return [
            'replies_count'   => 'integer',
            'last_reply_at'   => 'datetime',
            'participant_ids' => 'array',
        ];
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_message_id');
    }

    public function lastReply(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_reply_id');
    }
}
