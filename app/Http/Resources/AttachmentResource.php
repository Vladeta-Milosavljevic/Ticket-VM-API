<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $filename
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Database\Eloquent\Model $resource
 */
class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $this->getUrl(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    private function getUrl(): ?string
    {
        try {
            if ($this->disk === 's3') {
                return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(60));
            }

            return Storage::disk($this->disk)->url($this->path);
        } catch (\Throwable) {
            return null;
        }
    }
}
