<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, HasPushSubscriptions;

    // Always use the central database — never the tenant DB
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'display_name',
        'bio',
        'timezone',
        'status',
        'status_emoji',
        'status_text',
        'last_seen_at',
        'is_active',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** avatar_url must reach the frontend on every serialization (messages, members, presence). */
    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at'      => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'preferences'       => 'array',
        ];
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            // Use url() not asset() — asset() is overridden by Stancl Tenancy's
            // asset_helper_tenancy option and produces tenancy/assets/... paths.
            return url('/storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=5c7cfa&color=fff&size=128';
    }

    public function getDisplayNameAttribute($value): string
    {
        return $value ?: $this->name;
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }
}
