<?php

namespace App\Http\Requests\Collaboration;

use App\Domain\Collaboration\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeProjectMemberRoleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manageRoles', $this->route('project')) ?? false; }
    public function rules(): array { return ['role' => ['required', Rule::in([ProjectRole::ADMIN->value, ProjectRole::MEMBER->value])]]; }
}
