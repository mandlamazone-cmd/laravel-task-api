<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTasksRequest;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\V1\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    /**
     * List a project's tasks with optional filters and sorting.
     */
    public function index(ListTasksRequest $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        $filters = $request->validated();

        $tasks = $project->tasks()
            ->with(['creator', 'assignee'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assignee'] ?? null, fn (Builder $query, string $assignee) => match ($assignee) {
                'me' => $query->where('assignee_id', $request->user()->id),
                'none' => $query->whereNull('assignee_id'),
                default => $query->where('assignee_id', (int) $assignee),
            })
            ->when($request->boolean('overdue'), fn (Builder $query) => $query
                ->where('status', '!=', TaskStatus::Done)
                ->whereDate('due_on', '<', today()))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->whereRaw("title like ? escape '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
            ->tap(fn (Builder $query) => $this->applySort($query, $filters['sort'] ?? '-created_at'))
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $task = $project->tasks()->make($request->validated());
        $task->creator()->associate($request->user());
        $task->save();

        return (new TaskResource($task->load(['creator', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load(['creator', 'assignee']));
    }

    public function update(UpdateTaskRequest $request, Project $project, Task $task): TaskResource
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        return new TaskResource($task->load(['creator', 'assignee']));
    }

    public function destroy(Project $project, Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        $direction = Str::startsWith($sort, '-') ? 'desc' : 'asc';

        match (ltrim($sort, '-')) {
            'priority' => $query->orderByRaw($this->priorityWeightSql().' '.$direction),
            'due_on' => $query->orderByRaw('due_on is null')->orderBy('due_on', $direction),
            default => $query->orderBy(ltrim($sort, '-'), $direction),
        };

        $query->orderBy('id', $direction);
    }

    /**
     * Build a CASE expression from the enum so priorities sort by weight, not alphabetically.
     */
    private function priorityWeightSql(): string
    {
        $cases = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority) => "when '{$priority->value}' then {$priority->weight()}")
            ->implode(' ');

        return "case priority {$cases} end";
    }
}
