<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Determine whether the user can attach files to a ticket.
     */
    public function attach(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || $ticket->manager_id === $user->id
            || $ticket->agent_id === $user->id;
    }

    /**
     * Determine whether the user can attach files to a comment.
     */
    public function attachToComment(User $user, Comment $comment): bool
    {
        $ticket = $comment->ticket;

        return $user->isAdmin()
            || $ticket->manager_id === $user->id
            || $ticket->agent_id === $user->id;
    }

    /**
     * Determine whether the user can delete the attachment.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof Ticket) {
            return $user->isAdmin()
                || $attachable->manager_id === $user->id
                || $attachable->agent_id === $user->id;
        }

        if ($attachable instanceof Comment) {
            $ticket = $attachable->ticket;

            return $user->isAdmin()
                || $ticket->manager_id === $user->id
                || $ticket->agent_id === $user->id;
        }

        if ($attachable === null) {
            return false; // Orphaned attachment - parent was deleted
        }

        return false;
    }
}
