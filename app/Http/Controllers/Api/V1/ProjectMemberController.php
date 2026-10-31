<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddProjectMemberRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectMemberController extends Controller
{
    public function index(Project $project): AnonymousResourceCollection
    {
        Gate::authorize('view', $project);

        return UserResource::collection($project->members()->orderBy('name')->get());
    }

    /**
     * Add an existing user to the project by email.
     */
    public function store(AddProjectMemberRequest $request, Project $project): JsonResponse
    {
        $project->members()->attach(User::where('email', $request->validated('email'))->value('id'));

        return UserResource::collection($project->members()->orderBy('name')->get())
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove a member and unassign their open tasks in this project.
     */
    public function destroy(Project $project, User $user): Response
    {
        Gate::authorize('manageMembers', $project);

        if ($project->isOwnedBy($user)) {
            throw ValidationException::withMessages([
                'user' => 'The project owner cannot be removed.',
            ]);
        }

        abort_unless($project->hasMember($user), 404);

        DB::transaction(function () use ($project, $user): void {
            $project->tasks()->where('assignee_id', $user->id)->update(['assignee_id' => null]);
            $project->members()->detach($user->id);
        });

        return response()->noContent();
    }
}
