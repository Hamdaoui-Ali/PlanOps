<?php

namespace App\Http\Requests;

use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('exportAny', Project::class) ?? false;
    }

    public function rules(): array
    {
        return ['format' => ['nullable', Rule::in(['csv', 'json'])]];
    }
}
