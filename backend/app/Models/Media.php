<?php

namespace App\Models;

use App\Casts\Translatable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded photo and its resized copies.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $disk
 * @property string $base_path folder on the disk, e.g. "12/01JX…"
 * @property string $mime
 * @property int $width
 * @property int $height
 * @property int $bytes
 * @property array<int|string, string> $variants size (320, 640, 1280, jpg, og) → file in base_path
 * @property string|null $dominant_color "#a0522d"
 * @property string|null $placeholder tiny blurred preview (data URI)
 * @property array<string, string> $alt
 * @property int|null $created_by
 */
#[Fillable(['disk', 'base_path', 'mime', 'width', 'height', 'bytes', 'variants', 'dominant_color', 'placeholder', 'alt', 'created_by'])]
class Media extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'variants' => 'array',
            'alt' => Translatable::class,
        ];
    }

    /** @return array<int|string, string> size → public URL */
    public function urls(): array
    {
        $disk = Storage::disk($this->disk);

        return collect($this->variants)
            ->map(fn (string $file) => $disk->url($this->base_path.'/'.$file))
            ->all();
    }

    /** Removes the files; the row is deleted by the caller. */
    public function deleteFiles(): void
    {
        Storage::disk($this->disk)->deleteDirectory($this->base_path);
    }
}
