<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

// Writes in-app notifications to Laravel's standard `notifications` table (no email is sent)
class NotificationCentre
{
    public static function send(User|int|null $user, string $title, string $class = 'Information', ?string $link = null, ?string $body = null): void
    {
        $user = $user instanceof User ? $user : ($user ? User::find($user) : null);
        if (! $user) {
            return;
        }
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'csa.' . Str::slug($class),
            'data' => compact('title', 'class', 'link', 'body'),
        ]);
    }
}
