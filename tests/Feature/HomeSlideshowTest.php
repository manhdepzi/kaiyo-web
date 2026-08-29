<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\CMS\Application\Queries\PublicBannerReader;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\BannerRevision;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HomeSlideshowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_three_design_slides_with_a_three_second_interval_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-interval="3000"', false)
            ->assertSee('data-slide-prev', false)
            ->assertSee('data-slide-next', false)
            ->assertSee('viewBox="0 0 24 24"', false)
            ->assertSee('/images/design/home/banner-1.webp', false)
            ->assertSee('/images/design/home/banner-2.webp', false)
            ->assertSee('/images/design/home/banner-3.webp', false);
    }

    public function test_published_cms_slides_replace_defaults_and_are_sorted_by_admin_order(): void
    {
        $actor = UserAccount::factory()->create();
        foreach ([['later', 20], ['first', 10]] as [$code, $sortOrder]) {
            $banner = Banner::query()->create(['code' => $code, 'placement' => 'home.hero', 'status' => 'published']);
            $revision = BannerRevision::query()->create([
                'banner_id' => $banner->getKey(), 'revision_no' => 1, 'headline' => $code,
                'image_path' => '/images/design/home/banner-1.webp', 'sort_order' => $sortOrder,
                'integrity_hash' => random_bytes(32), 'created_by_user_account_id' => $actor->getKey(), 'published_at' => now(),
            ]);
            $banner->forceFill(['current_revision_id' => $revision->getKey(), 'published_revision_id' => $revision->getKey()])->save();
        }

        $slides = app(PublicBannerReader::class)->forPlacement('home.hero');

        self::assertSame(['first', 'later'], array_map(static fn ($slide): string => $slide->headline, $slides));
        $this->get(route('home'))->assertOk()->assertSeeInOrder(['first', 'later'])->assertDontSee('/images/design/home/banner-2.webp', false);
    }
}
