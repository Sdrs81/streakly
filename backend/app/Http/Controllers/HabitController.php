<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHabitRequest;
use App\Http\Requests\UpdateHabitRequest;
use App\Http\Resources\HabitResource;
use App\Models\Habit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class HabitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return HabitResource::collection($request->user()->habits()->oldest()->oldest('id')->get());
    }

    public function store(StoreHabitRequest $request): HabitResource
    {
        return new HabitResource($request->user()->habits()->create($request->validated()));
    }

    public function show(Habit $habit): HabitResource
    {
        Gate::authorize('view', $habit);

        return new HabitResource($habit);
    }

    public function update(UpdateHabitRequest $request, Habit $habit): HabitResource
    {
        $habit->update($request->validated());

        return new HabitResource($habit);
    }

    public function destroy(Habit $habit): Response
    {
        Gate::authorize('delete', $habit);

        $habit->delete();

        return response()->noContent();
    }
}
