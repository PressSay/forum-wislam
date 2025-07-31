<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAdmin(User $user): bool
    {
        if ($user->role == Role::ADMIN->value) {
            return true;
        }
        return false;
    }

    public function viewModerator(User $user): bool
    {
        if ($user->role == Role::MODERATOR->value) {
            return true;
        }
        return false;
    }

    public function viewModeratorOrAdmin(User $user): bool
    {
        if ($user->role == Role::MODERATOR->value || $user->role == Role::ADMIN->value) {
            return true;
        }
        return false;
    }

    public function userBlockedByUser(User $user1, User $user2): bool
    {
        $haveBlocked = DB::table('blocked_users')->where('user_id', '=', $user1->user_id)->where('blocked_user_id', '=', $user2->user_id)->get()->first();
        return $haveBlocked != null;
    }

    public function ModeratorHaveCategory(User $user, $category_id): bool
    {
        if ($user->role == Role::ADMIN->value) {
            return true;
        }
        if ($user->role != Role::MODERATOR->value) {
            return false;
        }
        $decentralizations = DB::table('decentralization_of_categories')->where('user_id', '=', $user->user_id)->get()->all();
        
        foreach ($decentralizations as $decentralization) {
            if ($decentralization->category_id == $category_id) {
                return true;
            }
        }
        return false;
    }

    public function isBlockedFromCategory($user, $user_id, $category_id) {
        $haveBlocked = DB::table('users_blocked_from_categories')
        ->where('user_id', $user_id)
        ->where('category_id', $category_id)    
        ->first();

        return $haveBlocked != null;
    }
}
