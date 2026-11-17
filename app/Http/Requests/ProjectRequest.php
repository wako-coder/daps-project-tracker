<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'budget' => ['required', 'string', 'regex:'.Money::PATTERN],
        ];
    }

    public function messages(): array
    {
        return ['budget.regex' => 'Enter the budget in Birr, for example 250000 or 250000.50.'];
    }

    /** The validated input as model attributes, with the budget in santim. */
    public function attributesForModel(): array
    {
        return [
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
            'budget_santim' => Money::toSantim($this->validated('budget')),
        ];
    }
}
