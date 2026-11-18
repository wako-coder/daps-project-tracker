<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'estimated_cost' => ['required', 'string', 'regex:'.Money::PATTERN],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
        ];
    }

    public function messages(): array
    {
        return ['estimated_cost.regex' => 'Enter the cost in Birr, for example 1500 or 1500.50.'];
    }

    /** The validated input as model attributes, with the cost in santim. */
    public function attributesForModel(): array
    {
        return [
            'title' => $this->validated('title'),
            'estimated_cost_santim' => Money::toSantim($this->validated('estimated_cost')),
            'status' => $this->validated('status'),
            'priority' => $this->validated('priority'),
        ];
    }
}
