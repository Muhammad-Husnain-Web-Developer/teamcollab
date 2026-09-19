<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelMember extends Model
{
    protected $fillable = [
        'channel_id', 'user_id', 'role', 'is_muted', 'last_read_at',
        'last_read_message_id', 'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'is_muted'             => 'boolean',
            'last_read_at'         => 'datetime',
            'last_read_message_id' => 'integer',
            'joined_at'            => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
