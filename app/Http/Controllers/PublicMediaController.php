<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PublicMediaController
{
    public function __invoke(string $asset): BinaryFileResponse|RedirectResponse
    {
        $media = MediaAsset::query()
            ->where('public_id', $asset)
            ->where('access_class', 'public')
            ->where('scan_status', 'clean')
            ->where('status', 'active')
            ->firstOrFail();
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->storage_key), 404);

        if ($this->isLocal($disk)) {
            return response()->file($disk->path($media->storage_key), [
                'Content-Type' => $media->detected_mime,
                'Content-Disposition' => 'inline; filename="'.addcslashes($media->original_name, '"\\').'"',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return redirect()->away($disk->temporaryUrl($media->storage_key, now()->addMinutes(10)));
    }

    private function isLocal(FilesystemAdapter $disk): bool
    {
        try {
            $disk->path('');

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }
}
