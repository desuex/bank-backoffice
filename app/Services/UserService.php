<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }
}
