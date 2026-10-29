<?php

namespace App\Http\Requests;

use App\Models\Habit;
use Illuminate\Foundation\Http\FormRequest;

class StoreHabitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Habit::NAME_MAX_LENGTH],
            'description' => ['nullable', 'string', 'max:'.Habit::DESCRIPTION_MAX_LENGTH],
        ];
    }
}
