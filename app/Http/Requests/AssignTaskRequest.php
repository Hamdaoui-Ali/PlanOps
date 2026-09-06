<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('assign', $this->route('task')) ?? false; }
    public function rules(): array { return ['assignee_id' => ['nullable', 'integer', 'exists:users,id']]; }
}
