<?php

namespace App\Models;

use Database\Factories\HabitCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// No date cast on completed_on: a cast rewrites the value to `Y-m-d 00:00:00` on save,
// so `Y-m-d` lookups would miss the stored row and hit the unique index.
#[Fillable(['completed_on'])]
class HabitCompletion extends Model
{
    /** @use HasFactory<HabitCompletionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Habit, $this>
     */
    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }
}
