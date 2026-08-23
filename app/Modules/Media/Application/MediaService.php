<?php

declare(strict_types=1);

namespace App\Modules\Media\Application;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Contracts\MalwareScanner;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class MediaService
{
    public function __construct(private PermissionAuthorizer $authorizer, private MalwareScanner $scanner) {}

    public function upload(UserAccount $actor, UploadedFile $upload, string $accessClass): MediaAsset
    {
        $this->authorizeManage($actor);
        if (! in_array($accessClass, ['public', 'private'], true) || ! $upload->isValid()) {
            throw new DomainException('Media upload is invalid.');
        }
        $path = $upload->getRealPath();
        $size = $upload->getSize();
        if ($path === false || $size === false || $size <= 0 || $size > (int) config('media.max_bytes')) {
            throw new DomainException('Media exceeds the approved size or is empty.');
        }
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (! is_string($detectedMime) || ! in_array($detectedMime, config('media.allowed_mimes'), true)) {
            throw new DomainException('Detected media content type is not allowed.');
        }
        $declaredMime = $upload->getClientMimeType();
        $extension = mb_strtolower($upload->getClientOriginalExtension(), 'UTF-8');
        $expectedExtensions = [
            'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'application/pdf' => ['pdf'],
        ];
        if ($declaredMime !== $detectedMime || ! in_array($extension, $expectedExtensions[$detectedMime], true)) {
            throw new DomainException('Declared MIME or extension does not match detected content.');
        }

        $disk = (string) config('media.disk');
        $id = (string) Str::ulid();
        $quarantineKey = 'quarantine/'.substr($id, 0, 2).'/'.$id.'.'.$extension;
        $asset = MediaAsset::query()->create([
            'public_id' => $id, 'disk' => $disk, 'storage_key' => $quarantineKey,
            'original_name' => basename(str_replace('\\', '/', $upload->getClientOriginalName())),
            'declared_mime' => $declaredMime, 'detected_mime' => $detectedMime, 'byte_size' => $size,
            'sha256' => hash_file('sha256', $path, true), 'access_class' => $accessClass,
            'scan_status' => 'pending', 'status' => 'quarantined', 'uploaded_by_user_account_id' => $actor->getKey(),
        ]);

        $scan = $this->scanner->scan($path);
        if (! $scan->clean) {
            $asset->forceFill(['scan_status' => 'rejected', 'status' => 'rejected', 'lock_version' => 1])->save();
            throw new DomainException('Media scan rejected the upload: '.$scan->code);
        }

        $finalKey = 'media/'.substr($id, 0, 2).'/'.$id.'/original.'.$extension;
        $stream = fopen($path, 'rb');
        if ($stream === false || ! Storage::disk($disk)->put($quarantineKey, $stream)) {
            $asset->forceFill(['scan_status' => 'failed', 'status' => 'rejected', 'lock_version' => 1])->save();
            throw new DomainException('Media quarantine storage failed.');
        }

        try {
            if (! Storage::disk($disk)->move($quarantineKey, $finalKey)) {
                throw new DomainException('Media promotion from quarantine failed.');
            }
            $asset->forceFill(['storage_key' => $finalKey, 'scan_status' => 'clean', 'status' => 'active', 'lock_version' => 1])->save();
            if (str_starts_with($detectedMime, 'image/')) {
                $this->createImageVariants($asset, (string) file_get_contents($path));
            }

            return $asset->refresh()->load('variants');
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete([$quarantineKey, $finalKey]);
            $asset->forceFill(['scan_status' => 'failed', 'status' => 'rejected', 'lock_version' => $asset->lock_version + 1])->save();
            throw $exception;
        }
    }

    public function attachToCatalog(UserAccount $actor, MediaAsset $asset, ?int $productId, ?int $variantId, string $purpose, int $sortOrder = 0): void
    {
        $this->authorizeManage($actor);
        if ($asset->status !== 'active' || (($productId === null) === ($variantId === null)) || ! in_array($purpose, ['primary', 'gallery', 'document'], true)) {
            throw new DomainException('Catalog media reference is invalid.');
        }
        DB::table('catalog_media_references')->insertOrIgnore([
            'product_id' => $productId, 'variant_id' => $variantId, 'media_asset_id' => $asset->getKey(),
            'purpose' => $purpose, 'sort_order' => max(0, $sortOrder),
            'identity_hash' => hash('sha256', ($productId ?? 0).'|'.($variantId ?? 0).'|'.$asset->getKey().'|'.$purpose, true),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function temporaryUrl(?UserAccount $actor, MediaAsset $asset): string
    {
        if ($asset->status !== 'active') {
            throw new DomainException('Media asset is not available.');
        }
        if ($asset->access_class === 'private') {
            if ($actor === null || ! $this->authorizer->allows($actor, 'media.assets.read_private', AuthorizationScope::module('media'))) {
                throw new AuthorizationException('Private media access is denied.');
            }
        }

        return Storage::disk($asset->disk)->temporaryUrl($asset->storage_key, now()->addMinutes((int) config('media.temporary_url_minutes')));
    }

    public function deleteOrphan(UserAccount $actor, MediaAsset $asset): void
    {
        $this->authorizeManage($actor);
        if ($asset->trashed()) {
            return;
        }
        DB::transaction(function () use ($asset): void {
            $locked = MediaAsset::withTrashed()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === 'deleted' || $locked->trashed()) {
                return;
            }
            if (DB::table('catalog_media_references')->where('media_asset_id', $locked->getKey())->exists()) {
                throw new DomainException('Referenced media cannot be deleted.');
            }
            $keys = $locked->variants()->pluck('storage_key')->all();
            $keys[] = $locked->storage_key;
            Storage::disk($locked->disk)->delete($keys);
            $locked->forceFill(['status' => 'deleted', 'lock_version' => $locked->lock_version + 1])->save();
            $locked->delete();
        }, 3);
    }

    private function authorizeManage(UserAccount $actor): void
    {
        if (! $this->authorizer->allows($actor, 'media.assets.manage', AuthorizationScope::module('media'))) {
            throw new AuthorizationException('Media management is denied.');
        }
    }

    private function createImageVariants(MediaAsset $asset, string $contents): void
    {
        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new DomainException('Image decoder rejected the content.');
        }
        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            foreach (config('media.image_variants') as $code => $maxWidth) {
                $configuredWidth = (int) $maxWidth;
                if ($configuredWidth <= 0) {
                    throw new DomainException('Image variant configuration is invalid.');
                }
                $width = min($configuredWidth, $sourceWidth);
                $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $target = imagecreatetruecolor($width, $height);
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
                ob_start();
                imagewebp($target, null, 82);
                $variantContents = ob_get_clean();
                imagedestroy($target);
                if (! is_string($variantContents) || $variantContents === '') {
                    throw new DomainException('Image variant encoding failed.');
                }
                $key = 'media/'.substr($asset->public_id, 0, 2).'/'.$asset->public_id.'/'.$code.'.webp';
                if (! Storage::disk($asset->disk)->put($key, $variantContents)) {
                    throw new DomainException('Image variant storage failed.');
                }
                $asset->variants()->create([
                    'variant_code' => (string) $code, 'disk' => $asset->disk, 'storage_key' => $key,
                    'width' => $width, 'height' => $height, 'byte_size' => strlen($variantContents), 'mime' => 'image/webp', 'created_at' => now(),
                ]);
            }
        } finally {
            imagedestroy($source);
        }
    }
}
