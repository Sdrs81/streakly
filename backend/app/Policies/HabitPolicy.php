<?php

namespace App\Policies;

use App\Models\Habit;
use App\Models\User;

// Index and store need no policy: they are scoped through $user->habits().
class HabitPolicy
{
    public function view(User $user, Habit $habit): bool
    {
        return $this->owns($user, $habit);
    }

    public function update(User $user, Habit $habit): bool
    {
        return $this->owns($user, $habit);
    }

    public function delete(User $user, Habit $habit): bool
    {
        return $this->owns($user, $habit);
    }

    private function owns(User $user, Habit $habit): bool
    {
        return $habit->user_id === $user->id;
    }
}
