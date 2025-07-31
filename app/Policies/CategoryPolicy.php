<?php

namespace App\Policies;


use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Enums\Role;

class CategoryPolicy
{
    public function update(User $user) {
        return $user->role == Role::ADMIN->value;
    }
}
