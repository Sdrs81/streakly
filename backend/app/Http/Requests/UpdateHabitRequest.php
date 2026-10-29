<?php

namespace App\Http\Requests;

use App\Models\Habit;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHabitRequest extends FormRequest
{
    // Runs before validation, so another user's habit gets 403 before any 422.
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('habit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // PUT must send the name; PATCH may omit it. Omitted fields are left unchanged.
        $nameRequirement = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'name' => [$nameRequirement, 'string', 'max:'.Habit::NAME_MAX_LENGTH],
            'description' => ['nullable', 'string', 'max:'.Habit::DESCRIPTION_MAX_LENGTH],
        ];
    }
}
