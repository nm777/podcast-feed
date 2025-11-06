<?php

namespace App\Policies;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeedPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Feed $feed)
    {
        return $user->id === $feed->user_id;
    }

    public function delete(User $user, Feed $feed)
    {
        return $user->id === $feed->user_id;
    }
}