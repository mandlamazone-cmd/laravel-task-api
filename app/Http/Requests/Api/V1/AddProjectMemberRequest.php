<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AddProjectMemberRequest extends FormRequest
{
    /**
     * Only the owner may add members; non-members get a 404.
     */
    public function authorize(): bool
    {
        return Gate::authorize('manageMembers', $this->route('project'))->allowed();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
                function (string $attribute, mixed $value, Closure $fail) use ($project): void {
                    if ($project->members()->where('email', $value)->exists()) {
                        $fail('This user is already a member of the project.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'No account uses this email address.',
        ];
    }
}
