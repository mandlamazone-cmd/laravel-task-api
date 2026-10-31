<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    public function view(User $user, Task $task): Response
    {
        return $task->project->hasMember($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Task $task): Response
    {
        return $this->view($user, $task);
    }

    /**
     * Only the task's creator or the project owner may delete it.
     */
    public function delete(User $user, Task $task): Response
    {
        if ($this->view($user, $task)->denied()) {
            return Response::denyAsNotFound();
        }

        return $task->creator_id === $user->id || $task->project->isOwnedBy($user)
            ? Response::allow()
            : Response::deny('Only the task creator or the project owner can delete this task.');
    }
}
