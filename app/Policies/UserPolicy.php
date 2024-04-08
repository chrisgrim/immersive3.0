<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function update(User $user, User $model)
    {
        return $model->id === $user->id || auth()->user()->type === 'a' || auth()->user()->type === 'm';
    }
}