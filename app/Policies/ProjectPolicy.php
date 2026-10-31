<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Non-members get a 404 so they cannot learn that the project exists.
     */
    public function view(User $user, Project $project): Response
    {
        return $project->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Project $project): Response
    {
        return $this->ownerOnly($user, $project);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->ownerOnly($user, $project);
    }

    public function manageMembers(User $user, Project $project): Response
    {
        return $this->ownerOnly($user, $project);
    }

    private function ownerOnly(User $user, Project $project): Response
    {
        if (! $project->hasMember($user)) {
            return Response::denyAsNotFound();
        }

        return $project->isOwnedBy($user)
            ? Response::allow()
            : Response::deny('Only the project owner can do this.');
    }
}
