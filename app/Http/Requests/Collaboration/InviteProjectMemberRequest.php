<?php

namespace App\Http\Requests\Collaboration;

use App\Domain\Collaboration\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMembers', $this->route('project')) ?? false;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in([ProjectRole::ADMIN->value, ProjectRole::MEMBER->value])]];
    }
}
