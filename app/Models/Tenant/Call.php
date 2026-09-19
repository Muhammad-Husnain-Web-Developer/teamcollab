<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'initiated_by', 'call_type', 'context_type', 'context_id',
        'status', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at'   => 'datetime',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function joinedParticipants(): HasMany
    {
        return $this->participants()->where('status', 'joined');
    }

    /** Everyone who should still hear about roster changes: not yet answered, or in the call. */
    public function reachableParticipants(): HasMany
    {
        return $this->participants()->whereIn('status', ['invited', 'joined']);
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }
}
