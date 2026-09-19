<?php

namespace App\Services;

use App\Events\ChannelMemberAdded;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\User;
use App\Policies\ChannelPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Built-in slash commands only — deliberately not an extensible bot/webhook
 * platform. Each command is a hardcoded branch below; adding a new one means
 * editing this file, not registering a plugin.
 */
class SlashCommandService
{
    private const COMMANDS = ['invite', 'mute', 'remind'];

    /**
     * Only ever matches a message that STARTS with a known command word —
     * a message that merely contains a "/" elsewhere, or starts with "/"
     * but isn't a recognized command, is left alone (parse() returns null
     * and the caller posts it as an ordinary text message).
     *
     * @return array{command: string, args: string}|null
     */
    public function parse(string $body): ?array
    {
        $body = trim($body);

        if (! str_starts_with($body, '/')) {
            return null;
        }

        if (! preg_match('/^\/([a-zA-Z]+)(?:\s+(.*))?$/su', $body, $matches)) {
            return null;
        }

        $command = strtolower($matches[1]);

        if (! in_array($command, self::COMMANDS, true)) {
            return null;
        }

        return ['command' => $command, 'args' => trim($matches[2] ?? '')];
    }

    /**
     * @return array{handled: bool, message: string, is_error: bool}
     */
    public function execute(string $command, string $args, Channel|Conversation $context, User $user): array
    {
        return match ($command) {
            'invite' => $this->invite($args, $context, $user),
            'mute'   => $this->mute($context, $user),
            'remind' => $this->remind($args, $context, $user),
        };
    }

    private function invite(string $args, Channel|Conversation $context, User $user): array
    {
        if (! $context instanceof Channel) {
            return $this->error('/invite only works in a channel, not a direct message.');
        }

        if (! preg_match('/@([\w.\-]+(?:\s[\w.\-]+)?)/u', $args, $m)) {
            return $this->error('Usage: /invite @name');
        }

        $name = trim($m[1]);
        $target = $this->findMemberByName($name);

        if (! $target) {
            return $this->error("Could not find a workspace member named \"{$name}\".");
        }

        if (! (new ChannelPolicy)->manageMembers($user, $context)) {
            return $this->error('Only channel admins can invite members.');
        }

        app(ChannelService::class)->addMember($context, $target, 'member');

        try {
            event(new ChannelMemberAdded($context->id, [
                'id'           => $target->id,
                'name'         => $target->name,
                'display_name' => $target->display_name,
                'avatar_url'   => $target->avatar_url,
                'role'         => 'member',
            ], $user->id, tenant('id')));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — membership is already saved.
        }

        return $this->ok(($target->display_name ?? $target->name) . " was added to #{$context->name}.");
    }

    private function mute(Channel|Conversation $context, User $user): array
    {
        if (! $context instanceof Channel) {
            return $this->error('/mute only works in a channel — mute a DM from its header menu instead.');
        }

        $member = ChannelMember::where('channel_id', $context->id)->where('user_id', $user->id)->first();

        if (! $member) {
            return $this->error('Join the channel before muting it.');
        }

        $member->update(['is_muted' => ! $member->is_muted]);

        return $this->ok($member->is_muted ? "Muted #{$context->name}." : "Unmuted #{$context->name}.");
    }

    /**
     * /remind me "text" in 10m (also accepts h/hr/hrs/hours, d/day/days).
     * Posts the reminder back into the same channel/DM at the scheduled
     * time, reusing Phase 6's scheduling machinery rather than a bespoke
     * reminders table.
     */
    private function remind(string $args, Channel|Conversation $context, User $user): array
    {
        if (! preg_match('/^me\s+"([^"]+)"\s+in\s+(\d+)\s*(m|min|mins|minutes|h|hr|hrs|hours|d|day|days)$/iu', $args, $m)) {
            return $this->error('Usage: /remind me "text" in 10m (or 2h, 1d)');
        }

        $text   = $m[1];
        $amount = (int) $m[2];
        $unit   = strtolower($m[3]);

        $minutes = match (true) {
            str_starts_with($unit, 'h') => $amount * 60,
            str_starts_with($unit, 'd') => $amount * 60 * 24,
            default                     => $amount,
        };

        if ($minutes < 1) {
            return $this->error('The reminder time must be at least 1 minute out.');
        }

        $data = [
            'body'          => '⏰ Reminder from ' . ($user->display_name ?? $user->name) . ": {$text}",
            'scheduled_for' => now()->addMinutes($minutes),
            'metadata'      => ['is_reminder' => true],
        ];

        if ($context instanceof Channel) {
            $data['channel_id'] = $context->id;
        } else {
            $data['conversation_id'] = $context->id;
        }

        app(SchedulingService::class)->schedule($data, $user);

        $unitLabel = rtrim($unit, 's');

        return $this->ok("Got it — I'll remind you in {$amount} {$unitLabel}" . ($amount === 1 ? '' : 's') . '.');
    }

    private function findMemberByName(string $name): ?User
    {
        $memberIds = DB::connection('mysql')->table('tenant_users')
            ->where('tenant_id', tenant('id'))
            ->pluck('user_id');

        $exact = User::whereIn('id', $memberIds)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->orWhereRaw('LOWER(display_name) = ?', [strtolower($name)]))
            ->first();

        if ($exact) {
            return $exact;
        }

        return User::whereIn('id', $memberIds)
            ->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%'])
                ->orWhereRaw('LOWER(display_name) LIKE ?', ['%' . strtolower($name) . '%']))
            ->first();
    }

    private function ok(string $message): array
    {
        return ['handled' => true, 'message' => $message, 'is_error' => false];
    }

    private function error(string $message): array
    {
        return ['handled' => true, 'message' => $message, 'is_error' => true];
    }
}
