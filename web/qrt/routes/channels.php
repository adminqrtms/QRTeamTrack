<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

// Personal channel: new-message notifications for the inbox and badges.
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// One channel per chat thread; only its two participants may listen.
Broadcast::channel('conversation.{id}', function ($user, $id) {
    $conversation = Conversation::find($id);

    return $conversation !== null && $conversation->hasParticipant($user);
});
