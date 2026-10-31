<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    /**
     * Sort keys clients may use; a leading "-" sorts descending.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['created_at', 'due_on', 'priority', 'title'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $sortOptions = collect(self::SORTABLE)->flatMap(fn (string $key) => [$key, "-{$key}"])->all();

        return [
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assignee' => ['nullable', 'string', 'regex:/^(me|none|\d+)$/'],
            'overdue' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in($sortOptions)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
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
            'assignee.regex' => 'The assignee must be "me", "none" or a user id.',
            'sort.in' => 'The sort must be one of: '.implode(', ', self::SORTABLE).' (prefix with "-" for descending).',
        ];
    }
}
