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

        $attachmentId = $attachment->id;
        $path = $attachment->path;
        $disk = $attachment->disk;

        $attachment->delete();

        Storage::disk($disk)->delete($path);

        Log::info('Attachment deleted', [
            'user_id' => $request->user()->id,
            'attachment_id' => $attachmentId,
            'path' => $path,
        ]);

        return response()->json(['message' => 'Attachment deleted successfully'], 200);
    }
}
