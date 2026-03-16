<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Delete an attachment.
     *
     * Authorization: User must be able to view the parent ticket/comment.
     */
    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $user = $request->user();
        $attachmentId = $attachment->id;
        $path = $attachment->path;
        $disk = $attachment->disk;

        // Delete the file from the storage directory.
        if (! Storage::disk($disk)->delete($path)) {
            return response()->json(['message' => 'Failed to delete file from storage'], 500);
        }
        // Delete the attachment from the database.
        $attachment->delete();
        // Log the attachment deletion for audit trail.
        Log::info('Attachment deleted', [
            'deleted_by_user_id' => $user->id,
            'deleted_by_user_email' => $user->email,
            'attachment_id' => $attachmentId,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
            'original_name' => $attachment->original_name,
            'path' => $path,
        ]);

        return response()->json(['message' => 'Attachment deleted successfully'], 200);
    }
}
