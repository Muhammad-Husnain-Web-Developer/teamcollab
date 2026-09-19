<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'channel_id', 'conversation_id', 'user_id', 'thread_id',
        'parent_id', 'body', 'type', 'is_edited', 'is_pinned',
        'edited_at', 'pinned_at', 'pinned_by', 'mentions',
        'attachments', 'metadata', 'replies_count',
    ];

    protected function casts(): array
    {
        return [
            'is_edited'   => 'boolean',
            'is_pinned'   => 'boolean',
            'edited_at'   => 'datetime',
            'pinned_at'   => 'datetime',
            'mentions'    => 'array',
            'attachments' => 'array',
            'metadata'    => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function thread(): HasOne
    {
        return $this->hasOne(MessageThread::class, 'parent_message_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function getGroupedReactionsAttribute(): array
    {
        $grouped = [];
        foreach ($this->reactions as $reaction) {
            $emoji = $reaction->emoji;
            if (!isset($grouped[$emoji])) {
                $grouped[$emoji] = ['emoji' => $emoji, 'count' => 0, 'users' => []];
            }
            $grouped[$emoji]['count']++;
            $grouped[$emoji]['users'][] = $reaction->user_id;
        }
        return array_values($grouped);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeInChannel($query, int $channelId)
    {
        return $query->where('channel_id', $channelId)->whereNull('parent_id');
    }
}
