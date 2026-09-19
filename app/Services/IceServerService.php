<?php

namespace App\Services;

use App\Models\User;

/**
 * Builds the RTCPeerConnection `iceServers` list. STUN is always included;
 * TURN is added when configured, with per-user credentials minted here so
 * the browser bundle never contains anything reusable.
 *
 * The time-limited scheme is coturn's `use-auth-secret` / REST API
 * convention: username = "<unix expiry>:<user id>", credential =
 * base64(HMAC-SHA1(secret, username)).
 */
class IceServerService
{
    /**
     * @return array<int, array{urls: string[], username?: string, credential?: string}>
     */
    public function forUser(User $user): array
    {
        $servers = [['urls' => $this->urls(config('services.stun.urls'))]];

        $turnUrls = $this->urls(config('services.turn.urls'));
        if ($turnUrls === []) {
            return $servers;
        }

        $secret = config('services.turn.secret');
        if (filled($secret)) {
            $username = now()->addSeconds($this->ttl())->timestamp . ':' . $user->id;

            $servers[] = [
                'urls'       => $turnUrls,
                'username'   => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
            ];

            return $servers;
        }

        if (filled(config('services.turn.username')) && filled(config('services.turn.credential'))) {
            $servers[] = [
                'urls'       => $turnUrls,
                'username'   => (string) config('services.turn.username'),
                'credential' => (string) config('services.turn.credential'),
            ];
        }

        return $servers;
    }

    /** Seconds the minted TURN credential stays valid (static ones never expire). */
    public function ttl(): int
    {
        return max(60, (int) config('services.turn.ttl', 3600));
    }

    public function hasTurn(): bool
    {
        return count($this->forUser(new User(['id' => 0]))) > 1;
    }

    /**
     * @return string[]
     */
    private function urls(?string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $csv))));
    }
}
