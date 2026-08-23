<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use DomainException;
use Illuminate\Support\Facades\DB;

final class SlugRedirectService
{
    public function replace(string $ownerType, int $ownerId, string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath) {
            throw new DomainException('A redirect cannot target itself.');
        }
        $newHash = hash('sha256', $newPath, true);
        DB::table('slug_redirects')->where('source_hash', $newHash)->where('active', true)->update([
            'active' => false,
            'updated_at' => now(),
        ]);
        DB::table('slug_redirects')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->where('active', true)->update([
            'target_path' => $newPath,
            'updated_at' => now(),
        ]);
        DB::table('slug_redirects')->insert([
            'source_path' => $oldPath,
            'target_path' => $newPath,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'status_code' => 301,
            'active' => true,
            'source_hash' => hash('sha256', $oldPath, true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
