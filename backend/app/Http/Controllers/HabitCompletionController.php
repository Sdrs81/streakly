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

        $habit->completions()->firstOrCreate(['completed_on' => HabitCalendar::today()->toDateString()]);

        return new HabitResource($habit->load('completions'));
    }

    public function destroy(Habit $habit): HabitResource
    {
        Gate::authorize('update', $habit);

        $habit->completions()->where('completed_on', HabitCalendar::today()->toDateString())->delete();

        return new HabitResource($habit->load('completions'));
    }
}
