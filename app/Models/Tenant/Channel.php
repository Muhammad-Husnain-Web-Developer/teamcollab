<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Channel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'type', 'created_by',
        'topic', 'is_archived', 'is_read_only', 'is_default',
        'icon', 'color', 'last_message_id', 'last_activity_at',
        'members_count', 'messages_count',
    ];

    protected function casts(): array
    {
        return [
            'is_archived'      => 'boolean',
            'is_read_only'     => 'boolean',
            'is_default'       => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (empty($channel->slug)) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'channel_members',
            'channel_id',
            'user_id'
        )->withPivot(['role', 'is_muted', 'last_read_at', 'joined_at'])->withTimestamps();
    }

    public function channelMembers(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function thread(): HasMany
    {
        return $this->hasMany(MessageThread::class);
    }

    public function isPublic(): bool
    {
        return $this->type === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    public function scopePublic($query)
    {
        return $query->where('type', 'public');
    }

    public function scopePrivate($query)
    {
        return $query->where('type', 'private');
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }
}
