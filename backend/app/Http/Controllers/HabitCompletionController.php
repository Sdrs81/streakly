<?php

namespace App\Http\Controllers;

use App\Http\Resources\HabitResource;
use App\Models\Habit;
use App\Support\HabitCalendar;
use Illuminate\Support\Facades\Gate;

// No FormRequest: these endpoints take no input, the server decides what "today" is.
class HabitCompletionController extends Controller
{
    public function store(Habit $habit): HabitResource
    {
        Gate::authorize('update', $habit);

        // createOrFirst inserts first and falls back to a lookup, so two simultaneous requests can't hit the unique index.
        $habit->completions()->createOrFirst(['completed_on' => HabitCalendar::today()->toDateString()]);

        return new HabitResource($habit->load('completions'));
    }

    public function destroy(Habit $habit): HabitResource
    {
        Gate::authorize('update', $habit);

        $habit->completions()->where('completed_on', HabitCalendar::today()->toDateString())->delete();

        return new HabitResource($habit->load('completions'));
    }
}
