<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Application\MediaService;
use App\Modules\Media\Contracts\MalwareScanner;
use App\Modules\Media\Domain\ScanResult;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::disk('local')->buildTemporaryUrlsUsing(fn (string $path, \DateTimeInterface $expires): string => 'https://media.test/'.$path.'?expires='.$expires->getTimestamp());
    }

    public function test_image_is_detected_scanned_promoted_and_variants_are_generated(): void
    {
        $actor = $this->actorWith('media.assets.manage');
        $asset = app(MediaService::class)->upload($actor, UploadedFile::fake()->image('unsafe name.png', 1600, 900), 'public');

        self::assertSame('image/png', $asset->detected_mime);
        self::assertSame('clean', $asset->scan_status);
        self::assertSame('active', $asset->status);
        self::assertStringNotContainsString('unsafe', $asset->storage_key);
        Storage::disk('local')->assertExists($asset->storage_key);
        self::assertCount(2, $asset->variants);
        foreach ($asset->variants as $variant) {
            Storage::disk('local')->assertExists($variant->storage_key);
            self::assertSame('image/webp', $variant->mime);
        }
    }

    public function test_mime_extension_mismatch_and_polyglot_content_fail_closed(): void
    {
        $actor = $this->actorWith('media.assets.manage');
        try {
            app(MediaService::class)->upload($actor, UploadedFile::fake()->image('photo.pdf'), 'public');
            self::fail('MIME mismatch must fail.');
        } catch (DomainException) {
            self::assertSame(0, MediaAsset::query()->count());
        }

        $polyglot = UploadedFile::fake()->image('photo.png');
        file_put_contents((string) $polyglot->getRealPath(), '<?php echo "owned";', FILE_APPEND);
        $this->expectException(DomainException::class);
        app(MediaService::class)->upload($actor, $polyglot, 'public');
    }

    public function test_scanner_failure_retains_rejected_evidence_and_publishes_nothing(): void
    {
        $this->app->bind(MalwareScanner::class, fn () => new class implements MalwareScanner
        {
            public function scan(string $absolutePath): ScanResult
            {
                return ScanResult::rejected('scanner_unavailable');
            }
        });

        try {
            app(MediaService::class)->upload($this->actorWith('media.assets.manage'), UploadedFile::fake()->image('photo.png'), 'private');
            self::fail('Scanner failure must fail closed.');
        } catch (DomainException) {
            $asset = MediaAsset::query()->firstOrFail();
            self::assertSame('rejected', $asset->status);
            self::assertSame('rejected', $asset->scan_status);
            Storage::disk('local')->assertMissing($asset->storage_key);
        }
    }

    public function test_private_temporary_url_requires_server_permission(): void
    {
        $manager = $this->actorWith('media.assets.manage');
        $asset = app(MediaService::class)->upload($manager, UploadedFile::fake()->image('private.png'), 'private');

        try {
            app(MediaService::class)->temporaryUrl(null, $asset);
            self::fail('Anonymous private access must fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $reader = $this->actorWith('media.assets.read_private');
        self::assertStringStartsWith('https://media.test/media/', app(MediaService::class)->temporaryUrl($reader, $asset));
    }

    public function test_reference_prevents_delete_then_orphan_cleanup_is_idempotent(): void
    {
        $manager = $this->actorWith('media.assets.manage');
        $asset = app(MediaService::class)->upload($manager, UploadedFile::fake()->image('catalog.png'), 'public');
        $category = Category::query()->create(['name' => 'Media', 'slug' => 'media', 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Media product', 'slug' => 'media-product']);
        app(MediaService::class)->attachToCatalog($manager, $asset, (int) $product->getKey(), null, 'primary');

        try {
            app(MediaService::class)->deleteOrphan($manager, $asset);
            self::fail('Referenced media cannot be deleted.');
        } catch (DomainException) {
            self::assertTrue(Storage::disk('local')->exists($asset->storage_key));
        }

        \DB::table('catalog_media_references')->where('media_asset_id', $asset->getKey())->delete();
        app(MediaService::class)->deleteOrphan($manager, $asset);
        app(MediaService::class)->deleteOrphan($manager, $asset);
        Storage::disk('local')->assertMissing($asset->storage_key);
        self::assertSoftDeleted('media_assets', ['id' => $asset->getKey(), 'status' => 'deleted']);
    }

    private function actorWith(string $permissionCode): UserAccount
    {
        $actor = UserAccount::factory()->create([
            'two_factor_secret' => encrypt('media-secret'), 'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(), 'two_factor_enabled_at' => now(),
        ]);
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module('media')->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Media test authority.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);

        return $actor;
    }
}
