<?php

namespace App\Policies;

use App\Models\Thread;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Auth\Access\Response;

class ThreadPolicy
{
    public function update(User $user, Thread $thread)
    {
        if ($user->user_id === $thread->user_id) {
            return true;
        }
        if ($user->role == Role::ADMIN->value) {
            return true;
        }
        if ($user->role != Role::MODERATOR->value) {
            return false;
        }
        foreach ($user->categories as $category) {
            if ($thread->category_id == $category->category_id) {
                return true;
            }
        }
        return false;
    }

    public function pinOrLock(User $user, Thread $thread)
    {
        if ($user->role == Role::ADMIN->value) {
            return true;
        }
        if ($user->role != Role::MODERATOR->value) {
            return false;
        }
        foreach ($user->categories as $category) {
            if ($thread->category_id == $category->category_id) {
                return true;
            }
        }
        return false;
    }


}
