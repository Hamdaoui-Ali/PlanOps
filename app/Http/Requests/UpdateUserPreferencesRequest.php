<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'timezone' => ['sometimes', 'required', 'timezone'],
            'week_start_day' => ['sometimes', 'required', 'in:MONDAY,SUNDAY'],
            'theme' => ['sometimes', 'required', 'in:SYSTEM,LIGHT,DARK'],
            'density' => ['sometimes', 'required', 'in:COMFORTABLE,COMPACT'],
        ];
    }
}
