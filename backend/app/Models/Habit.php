<?php

namespace App\Models;

use App\Services\StreakCalculator;
use App\Support\HabitCalendar;
use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class Habit extends Model
{
    /** @use HasFactory<HabitFactory> */
    use HasFactory;

    public const NAME_MAX_LENGTH = 100;

    public const DESCRIPTION_MAX_LENGTH = 500;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<HabitCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(HabitCompletion::class);
    }

    public function currentStreak(): int
    {
        return (new StreakCalculator)->calculate(
            $this->completions->pluck('completed_on'),
            HabitCalendar::today(),
        );
    }

    public function isCompletedToday(): bool
    {
        return $this->completions->contains('completed_on', HabitCalendar::today()->toDateString());
    }
}
