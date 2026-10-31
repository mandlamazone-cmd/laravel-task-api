<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    /**
     * List the projects the signed-in user belongs to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->visibleTo($request->user())
            ->with('owner')
            ->withCount(['members', 'openTasks'])
            ->latest('id')
            ->paginate(20);

        return ProjectResource::collection($projects);
    }

    /**
     * Create a project owned by the signed-in user.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $request->user()->ownedProjects()->create($request->validated());

        return (new ProjectResource($project->load('owner', 'members')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return new ProjectResource($project->load('owner', 'members')->loadCount('openTasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        Gate::authorize('update', $project);

        $project->update($request->validated());

        return new ProjectResource($project->load('owner', 'members'));
    }

    /**
     * Delete a project and all of its tasks.
     */
    public function destroy(Project $project): Response
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }
}
