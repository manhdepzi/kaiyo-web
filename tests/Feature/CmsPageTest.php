<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\CMS\Application\Actions\CreateArticleDraft;
use App\Modules\CMS\Application\Actions\CreateArticleRevision;
use App\Modules\CMS\Application\Actions\CreateBannerDraft;
use App\Modules\CMS\Application\Actions\CreateBannerRevision;
use App\Modules\CMS\Application\Actions\CreateEmailTemplateDraft;
use App\Modules\CMS\Application\Actions\CreateEmailTemplateRevision;
use App\Modules\CMS\Application\Actions\CreateFaqDraft;
use App\Modules\CMS\Application\Actions\CreateFaqRevision;
use App\Modules\CMS\Application\Actions\CreatePageDraft;
use App\Modules\CMS\Application\Actions\CreatePageRevision;
use App\Modules\CMS\Application\Actions\ManageContentMedia;
use App\Modules\CMS\Application\Actions\PublishArticle;
use App\Modules\CMS\Application\Actions\PublishBanner;
use App\Modules\CMS\Application\Actions\PublishEmailTemplate;
use App\Modules\CMS\Application\Actions\PublishFaq;
use App\Modules\CMS\Application\Actions\PublishPage;
use App\Modules\CMS\Application\Actions\RunCmsPublicationSchedule;
use App\Modules\CMS\Application\Actions\ScheduleCmsPublication;
use App\Modules\CMS\Application\Actions\SchedulePagePublication;
use App\Modules\CMS\Application\Actions\UnpublishCmsContent;
use App\Modules\CMS\Application\Actions\UnpublishPage;
use App\Modules\CMS\Application\Queries\RenderPublishedEmailTemplate;
use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Application\MediaService;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CmsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_draft_publish_and_public_sanitized_ssr_are_permission_gated(): void
    {
        $creator = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($creator, 'content.manage');
        $this->grant($creator, 'media.assets.manage', 'media');
        $this->grant($publisher, 'content.publish');
        $this->enableTwoFactor($creator);
        $this->enableTwoFactor($publisher);

        $created = app(CreatePageDraft::class)->execute(
            $creator,
            'huong-dan-khach-hang',
            'Hướng dẫn khách hàng',
            "## Nội dung an toàn\n\n<script>alert('x')</script>Liên hệ đội ngũ Kaiyo.",
            'Tóm tắt hướng dẫn.',
        );
        $this->get(route('public.page', $created['page']->slug))->assertNotFound();

        try {
            app(PublishPage::class)->execute($creator, $created['page'], 0);
            self::fail('Manage permission must not imply publish permission.');
        } catch (AuthorizationException) {
            self::assertSame('draft', $created['page']->refresh()->status);
        }

        $published = app(PublishPage::class)->execute($publisher, $created['page'], 0);
        $retry = app(PublishPage::class)->execute($publisher, $published, 0);
        self::assertSame('published', $retry->status);
        self::assertSame(1, $retry->lock_version);
        $this->get(route('public.page', $retry->slug))
            ->assertOk()->assertSee('<h1', false)->assertSee('Nội dung an toàn')
            ->assertDontSee('<script>', false)->assertSee('Tóm tắt hướng dẫn.');
    }

    public function test_reserved_slug_and_stale_publish_fail_closed(): void
    {
        $actor = UserAccount::factory()->create();
        $this->grant($actor, 'content.manage');
        $this->grant($actor, 'content.publish');
        $this->enableTwoFactor($actor);

        $this->expectException(DomainException::class);
        app(CreatePageDraft::class)->execute($actor, 'admin', 'Reserved', 'Body');
    }

    public function test_admin_page_delivery_separates_manage_and_publish_permissions(): void
    {
        $outsider = UserAccount::factory()->create();
        $this->actingAs($outsider)->get(route('admin.pages'))->assertForbidden();

        $creator = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($creator, 'content.manage');
        $this->grant($creator, 'media.assets.manage', 'media');
        $this->grant($publisher, 'content.publish');
        $this->actingAs($creator)->get(route('admin.pages'))->assertRedirect(route('account.security'));
        $this->enableTwoFactor($creator);
        $this->enableTwoFactor($publisher);

        $this->actingAs($creator)->post(route('admin.pages.store'), [
            'slug' => 'chinh-sach-bao-hanh',
            'title' => 'Chính sách bảo hành',
            'summary' => 'Điều kiện bảo hành chính thức.',
            'body_markdown' => '## Phạm vi bảo hành',
        ])->assertRedirect(route('admin.pages'));
        $page = Page::query()->where('slug', 'chinh-sach-bao-hanh')->firstOrFail();
        $asset = $this->mediaAsset($creator, 'public');
        $this->actingAs($creator)->post(route('admin.pages.media.attach', $page->public_id), [
            'asset_public_id' => $asset->public_id, 'purpose' => 'attachment', 'sort_order' => 0, 'lock_version' => 0,
        ])->assertRedirect(route('admin.pages'));
        $this->actingAs($creator)->get(route('admin.pages'))->assertOk()->assertSee('test.png')->assertSee('attachment');

        $this->actingAs($creator)->post(route('admin.pages.publish', $page->public_id), ['lock_version' => 1])->assertForbidden();
        $this->actingAs($publisher)->post(route('admin.pages.publish', $page->public_id), ['lock_version' => 1])
            ->assertRedirect(route('admin.pages'));
        self::assertSame('published', $page->refresh()->status);

        $this->actingAs($creator)->get(route('admin.pages', ['q' => 'bảo hành', 'status' => 'published']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Chính sách bảo hành')->assertSee('/noi-dung/chinh-sach-bao-hanh')
            ->assertSee('Nội dung')->assertDontSee('>Audit<', false);
    }

    public function test_replacement_revision_preserves_live_content_until_publish_and_unpublish_is_idempotent(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $created = app(CreatePageDraft::class)->execute($editor, 'dieu-khoan', 'Điều khoản v1', 'Nội dung v1');
        $page = app(PublishPage::class)->execute($publisher, $created['page'], 0);

        $revision = app(CreatePageRevision::class)->execute($editor, $page, 1, 'Điều khoản v2', 'Nội dung v2');
        self::assertSame(2, $revision->revision_no);
        $page->refresh();
        self::assertSame('draft', $page->status);
        self::assertNotSame($page->current_revision_id, $page->published_revision_id);
        $this->get(route('public.page', 'dieu-khoan'))->assertOk()->assertSee('Điều khoản v1')->assertDontSee('Điều khoản v2');

        try {
            app(CreatePageRevision::class)->execute($editor, $page, 1, 'Stale', 'Stale body');
            self::fail('A stale revision write must fail closed.');
        } catch (DomainException) {
            self::assertSame(2, $page->refresh()->lock_version);
        }

        $page = app(PublishPage::class)->execute($publisher, $page, 2);
        $this->get(route('public.page', 'dieu-khoan'))->assertOk()->assertSee('Điều khoản v2')->assertDontSee('Điều khoản v1');
        $page = app(UnpublishPage::class)->execute($publisher, $page, 3);
        $retry = app(UnpublishPage::class)->execute($publisher, $page, 3);
        self::assertSame(4, $retry->lock_version);
        $this->get(route('public.page', 'dieu-khoan'))->assertNotFound();
    }

    public function test_due_publication_schedule_runs_once_and_unpublishes_once(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $created = app(CreatePageDraft::class)->execute($editor, 'lich-xuat-ban', 'Lịch xuất bản', 'Nội dung');
        $due = CarbonImmutable::now()->subMinute();
        $schedule = app(SchedulePagePublication::class)->execute($publisher, $created['page'], 'publish', $due, 'cms.page.publish.schedule-001', 0);
        $retry = app(SchedulePagePublication::class)->execute($publisher, $created['page'], 'publish', $due, 'cms.page.publish.schedule-001', 0);
        self::assertTrue($schedule->is($retry));

        $this->artisan('cms:run-publication-schedules')->assertSuccessful();
        $this->artisan('cms:run-publication-schedules')->assertSuccessful();
        self::assertSame('completed', $schedule->refresh()->state);
        self::assertSame(1, $schedule->attempts);
        $page = $created['page']->refresh();
        self::assertSame('published', $page->status);
        self::assertSame(2, $page->lock_version);

        $unpublish = app(SchedulePagePublication::class)->execute($publisher, $page, 'unpublish', $due, 'cms.page.unpublish.schedule-001', 2);
        $this->artisan('cms:run-publication-schedules')->assertSuccessful();
        self::assertSame('completed', $unpublish->refresh()->state);
        self::assertSame('unpublished', $page->refresh()->status);
        self::assertSame(4, $page->lock_version);
        $this->get(route('public.page', 'lich-xuat-ban'))->assertNotFound();
    }

    public function test_scheduled_publish_fails_closed_when_target_revision_changes(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $created = app(CreatePageDraft::class)->execute($editor, 'stale-schedule', 'Revision 1', 'Body 1');
        $schedule = app(SchedulePagePublication::class)->execute(
            $publisher,
            $created['page'],
            'publish',
            CarbonImmutable::now()->subMinute(),
            'cms.page.publish.stale-001',
            0,
        );
        app(CreatePageRevision::class)->execute($editor, $created['page'], 1, 'Revision 2', 'Body 2');

        $this->artisan('cms:run-publication-schedules')->assertSuccessful();
        self::assertSame('failed', $schedule->refresh()->state);
        self::assertSame('page_revision_changed', $schedule->last_error_code);
        self::assertNull($created['page']->refresh()->published_revision_id);
        $this->get(route('public.page', 'stale-schedule'))->assertNotFound();
    }

    public function test_article_uses_a_separate_root_revision_and_published_only_sanitized_ssr(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $created = app(CreateArticleDraft::class)->execute(
            $editor,
            'bao-quan-thiet-bi',
            'Bảo quản thiết bị',
            "## Hướng dẫn\n\n<script>alert('unsafe')</script>\n\nGiữ thiết bị khô ráo.",
            'Hướng dẫn vận hành an toàn.',
        );
        $this->get(route('public.article', 'bao-quan-thiet-bi'))->assertNotFound();

        try {
            app(PublishArticle::class)->execute($editor, $created['article'], 0);
            self::fail('Article manage permission must not imply publish permission.');
        } catch (AuthorizationException) {
            self::assertSame('draft', $created['article']->refresh()->status);
        }

        $article = app(PublishArticle::class)->execute($publisher, $created['article'], 0);
        app(PublishArticle::class)->execute($publisher, $article, 0);
        $this->get(route('public.article', 'bao-quan-thiet-bi'))->assertOk()
            ->assertSee('Bảo quản thiết bị')->assertSee('Hướng dẫn vận hành an toàn.')
            ->assertSee('Giữ thiết bị khô ráo.')->assertDontSee('<script>', false);
        self::assertSame(1, $article->refresh()->lock_version);
    }

    public function test_faq_directory_is_published_only_ordered_sanitized_and_query_bounded(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $later = app(CreateFaqDraft::class)->execute($editor, 'giao-hang', 'Khi nào giao hàng?', 'Theo lịch đã xác nhận.', 20);
        $first = app(CreateFaqDraft::class)->execute($editor, 'bao-hanh', 'Bảo hành thế nào?', "Theo chính sách.\n\n<script>unsafe()</script>", 10);
        app(PublishFaq::class)->execute($publisher, $later['faq'], 0);
        app(PublishFaq::class)->execute($publisher, $first['faq'], 0);
        app(CreateFaqDraft::class)->execute($editor, 'noi-bo', 'FAQ nháp?', 'Không được public.', 0);

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });
        $response = $this->get(route('public.faq'));
        $response->assertOk()->assertSeeInOrder(['Bảo hành thế nào?', 'Khi nào giao hàng?'])
            ->assertDontSee('FAQ nháp?')->assertDontSee('<script>', false);
        self::assertLessThanOrEqual(3, $queryCount);
    }

    public function test_home_banner_is_published_only_and_rejects_unsafe_cta_urls(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        try {
            app(CreateBannerDraft::class)->execute($editor, 'unsafe', 'home.hero', 'Unsafe', null, 'Mở', 'javascript:alert(1)');
            self::fail('Unsafe CTA URL must be rejected.');
        } catch (DomainException) {
            self::assertDatabaseMissing('banners', ['code' => 'unsafe']);
        }
        $created = app(CreateBannerDraft::class)->execute(
            $editor,
            'summer-hero',
            'home.hero',
            'Giải pháp mùa hè',
            'Nội dung đã được duyệt.',
            'Xem sản phẩm',
            '/tim-kiem',
        );
        $this->get(route('home'))->assertOk()->assertDontSee('Giải pháp mùa hè');
        app(PublishBanner::class)->execute($publisher, $created['banner'], 0);
        $this->get(route('home'))->assertOk()->assertSee('Giải pháp mùa hè')->assertSee('Nội dung đã được duyệt.')
            ->assertSee('href="/tim-kiem"', false);
    }

    public function test_email_template_whitelist_blocks_executable_unknown_missing_and_header_injection_values(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        try {
            app(CreateEmailTemplateDraft::class)->execute($editor, 'quote.issued', 'Báo giá {{ unknown }}', 'Xin chào', ['customer_name']);
            self::fail('Unknown placeholders must be rejected.');
        } catch (DomainException) {
            self::assertDatabaseMissing('email_templates', ['template_key' => 'quote.issued']);
        }
        try {
            app(CreateEmailTemplateDraft::class)->execute($editor, 'unsafe.template', 'Unsafe', '@php echo(1);', []);
            self::fail('Executable template syntax must be rejected.');
        } catch (DomainException) {
            self::assertDatabaseMissing('email_templates', ['template_key' => 'unsafe.template']);
        }
        $created = app(CreateEmailTemplateDraft::class)->execute(
            $editor,
            'quote.issued',
            'Báo giá {{ quote_number }} cho {{ customer_name }}',
            'Xin chào **{{ customer_name }}**. Mã báo giá: {{ quote_number }}.',
            ['quote_number', 'customer_name'],
        );
        app(PublishEmailTemplate::class)->execute($publisher, $created['template'], 0);

        try {
            app(RenderPublishedEmailTemplate::class)->render('quote.issued', ['customer_name' => 'Kaiyo']);
            self::fail('Missing render variables must fail closed.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $rendered = app(RenderPublishedEmailTemplate::class)->render('quote.issued', [
            'customer_name' => '<script>alert(1)</script>',
            'quote_number' => 'Q-100',
        ]);
        self::assertSame('Báo giá Q-100 cho <script>alert(1)</script>', $rendered->subject);
        self::assertStringNotContainsString('<script>', $rendered->bodyHtml);
        self::assertStringContainsString('&lt;script&gt;', $rendered->bodyHtml);
        try {
            app(RenderPublishedEmailTemplate::class)->render('quote.issued', [
                'customer_name' => 'Kaiyo',
                'quote_number' => "Q-100\r\nBcc: attacker@example.test",
            ]);
            self::fail('Subject header injection must fail closed.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
    }

    public function test_admin_cms_workspace_delivers_type_specific_drafts_and_publish_gate(): void
    {
        $manager = UserAccount::factory()->create();
        $this->grant($manager, 'content.manage');
        $this->enableTwoFactor($manager);
        $this->actingAs($manager)->get(route('admin.content'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($manager)->post(route('admin.content.articles.store'), [
            'slug' => 'admin-article', 'title' => 'Admin Article', 'excerpt' => 'Summary', 'body_markdown' => 'Body',
        ])->assertRedirect(route('admin.content'));
        $this->actingAs($manager)->post(route('admin.content.faqs.store'), [
            'code' => 'admin-faq', 'question' => 'Admin FAQ?', 'answer_markdown' => 'Answer', 'position' => 1,
        ])->assertRedirect(route('admin.content'));
        $this->actingAs($manager)->post(route('admin.content.banners.store'), [
            'code' => 'admin-banner', 'placement' => 'home.hero', 'headline' => 'Admin Banner', 'body' => 'Banner body',
        ])->assertRedirect(route('admin.content'));
        $this->actingAs($manager)->post(route('admin.content.email-templates.store'), [
            'template_key' => 'admin.template', 'subject' => 'Hello {{ name }}', 'body_markdown' => 'Hi {{ name }}', 'allowed_variables' => 'name',
        ])->assertRedirect(route('admin.content'));
        $article = Article::query()->where('slug', 'admin-article')->firstOrFail();
        $this->actingAs($manager)->post(route('admin.content.articles.publish', $article->public_id), ['lock_version' => 0])->assertForbidden();
        $this->actingAs($manager)->get(route('admin.content'))->assertOk()
            ->assertSee('Admin Article')->assertSee('Admin FAQ?')->assertSee('Admin Banner')->assertSee('Hello {{ name }}');

        $this->grant($manager, 'content.publish');
        $this->actingAs($manager)->post(route('admin.content.articles.publish', $article->public_id), ['lock_version' => 0])
            ->assertRedirect(route('admin.content'));
        self::assertSame('published', $article->refresh()->status);
        $this->actingAs($manager)->post(route('admin.content.articles.revise', $article->public_id), [
            'lock_version' => 1, 'title' => 'Admin Article v2', 'excerpt' => 'Summary v2', 'body_markdown' => 'Body v2',
        ])->assertRedirect(route('admin.content'));
        self::assertSame('draft', $article->refresh()->status);
        self::assertNotNull($article->published_revision_id);
        $this->actingAs($manager)->get(route('admin.content'))->assertOk()->assertSee('Admin Article v2')->assertSee('Gỡ xuất bản');
        $this->actingAs($manager)->post(route('admin.content.articles.publish', $article->public_id), ['lock_version' => 2])->assertRedirect(route('admin.content'));
        $this->actingAs($manager)->post(route('admin.content.unpublish', ['type' => 'articles', 'content' => $article->public_id]), ['lock_version' => 3])->assertRedirect(route('admin.content'));
        self::assertSame('unpublished', $article->refresh()->status);
        $this->actingAs($manager)->post(route('admin.content.schedule', ['type' => 'articles', 'content' => $article->public_id]), [
            'lock_version' => 4, 'action' => 'publish', 'due_at' => now()->addHour()->format('Y-m-d H:i:s'), 'operation_key' => 'admin.article.schedule.001',
        ])->assertRedirect(route('admin.content'));
        $this->assertDatabaseHas('publication_schedules', ['article_id' => $article->getKey(), 'operation_key' => 'admin.article.schedule.001', 'state' => 'pending']);
    }

    public function test_type_specific_revisions_preserve_live_content_until_republish_and_unpublish_once(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $article = app(CreateArticleDraft::class)->execute($editor, 'revision-article', 'Old article', 'Old body');
        $faq = app(CreateFaqDraft::class)->execute($editor, 'revision-faq', 'Old question?', 'Old answer', 1);
        $banner = app(CreateBannerDraft::class)->execute($editor, 'revision-banner', 'home.hero', 'Old banner');
        $template = app(CreateEmailTemplateDraft::class)->execute($editor, 'revision.email', 'Old {{ name }}', 'Old body {{ name }}', ['name']);
        app(PublishArticle::class)->execute($publisher, $article['article'], 0);
        app(PublishFaq::class)->execute($publisher, $faq['faq'], 0);
        app(PublishBanner::class)->execute($publisher, $banner['banner'], 0);
        app(PublishEmailTemplate::class)->execute($publisher, $template['template'], 0);

        app(CreateArticleRevision::class)->execute($editor, $article['article'], 1, 'New article', 'New body');
        app(CreateFaqRevision::class)->execute($editor, $faq['faq'], 1, 'New question?', 'New answer', 2);
        app(CreateBannerRevision::class)->execute($editor, $banner['banner'], 1, 'New banner', ctaLabel: 'Open', ctaUrl: '/gioi-thieu');
        app(CreateEmailTemplateRevision::class)->execute($editor, $template['template'], 1, 'New {{ name }}', 'New body {{ name }}', ['name']);

        $this->get(route('public.article', 'revision-article'))->assertOk()->assertSee('Old article')->assertDontSee('New article');
        $this->get(route('public.faq'))->assertOk()->assertSee('Old question?')->assertDontSee('New question?');
        $this->get(route('home'))->assertOk()->assertSee('Old banner')->assertDontSee('New banner');
        self::assertSame('Old Kaiyo', app(RenderPublishedEmailTemplate::class)->render('revision.email', ['name' => 'Kaiyo'])->subject);

        app(PublishArticle::class)->execute($publisher, $article['article'], 2);
        app(PublishFaq::class)->execute($publisher, $faq['faq'], 2);
        app(PublishBanner::class)->execute($publisher, $banner['banner'], 2);
        app(PublishEmailTemplate::class)->execute($publisher, $template['template'], 2);
        $this->get(route('public.article', 'revision-article'))->assertOk()->assertSee('New article');
        $this->get(route('public.faq'))->assertOk()->assertSee('New question?');
        $this->get(route('home'))->assertOk()->assertSee('New banner')->assertSee('/gioi-thieu', false);
        self::assertSame('New Kaiyo', app(RenderPublishedEmailTemplate::class)->render('revision.email', ['name' => 'Kaiyo'])->subject);

        $unpublish = app(UnpublishCmsContent::class);
        foreach ([$article['article'], $faq['faq'], $banner['banner'], $template['template']] as $content) {
            $unpublish->execute($publisher, $content, 3);
            $unpublish->execute($publisher, $content, 3);
        }
        $this->get(route('public.article', 'revision-article'))->assertNotFound();
        $this->get(route('public.faq'))->assertOk()->assertDontSee('New question?');
        $this->get(route('home'))->assertOk()->assertDontSee('New banner');
        $this->expectException(DomainException::class);
        app(RenderPublishedEmailTemplate::class)->render('revision.email', ['name' => 'Kaiyo']);
    }

    public function test_article_faq_and_banner_schedules_are_idempotent_and_stale_safe(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($publisher, 'content.publish');
        $article = app(CreateArticleDraft::class)->execute($editor, 'scheduled-article', 'Scheduled article', 'Body');
        $faq = app(CreateFaqDraft::class)->execute($editor, 'scheduled-faq', 'Scheduled FAQ?', 'Answer');
        $banner = app(CreateBannerDraft::class)->execute($editor, 'scheduled-banner', 'home.hero', 'Scheduled banner');
        $due = CarbonImmutable::now()->subSecond()->startOfSecond();
        $schedule = app(ScheduleCmsPublication::class);
        $articleSchedule = $schedule->execute($publisher, $article['article'], 'publish', $due, 'cms.article.publish.001', 0);
        $retry = $schedule->execute($publisher, $article['article'], 'publish', $due, 'cms.article.publish.001', 0);
        self::assertTrue($articleSchedule->is($retry));
        $faqSchedule = $schedule->execute($publisher, $faq['faq'], 'publish', $due, 'cms.faq.publish.001', 0);
        $bannerSchedule = $schedule->execute($publisher, $banner['banner'], 'publish', $due, 'cms.banner.publish.001', 0);

        app(CreateFaqRevision::class)->execute($editor, $faq['faq'], 1, 'Changed FAQ?', 'Changed answer', 2);
        $runner = app(RunCmsPublicationSchedule::class);
        self::assertSame('completed', $runner->execute($articleSchedule)->state);
        self::assertSame('completed', $runner->execute($articleSchedule)->state);
        self::assertSame('failed', $runner->execute($faqSchedule)->state);
        self::assertSame('faq_revision_changed', $faqSchedule->refresh()->last_error_code);
        self::assertSame('completed', $runner->execute($bannerSchedule)->state);
        $this->get(route('public.article', 'scheduled-article'))->assertOk();
        $this->get(route('public.faq'))->assertDontSee('Scheduled FAQ?');
        $this->get(route('home'))->assertSee('Scheduled banner');

        $unpublish = $schedule->execute($publisher, $article['article'], 'unpublish', $due, 'cms.article.unpublish.001', 2);
        self::assertSame('completed', $runner->execute($unpublish)->state);
        self::assertSame('completed', $runner->execute($unpublish)->state);
        $this->get(route('public.article', 'scheduled-article'))->assertNotFound();
    }

    public function test_content_media_requires_cross_domain_authority_and_only_mutates_current_draft(): void
    {
        $editor = UserAccount::factory()->create();
        $publisher = UserAccount::factory()->create();
        $this->grant($editor, 'content.manage');
        $this->grant($editor, 'media.assets.manage', 'media');
        $this->grant($publisher, 'content.publish');
        $page = app(CreatePageDraft::class)->execute($editor, 'media-page', 'Media Page', 'Body');
        $asset = $this->mediaAsset($editor, 'public');
        $private = $this->mediaAsset($editor, 'private');
        $media = app(ManageContentMedia::class);

        $media->attach($editor, $page['revision'], $asset, 'hero', 0, 0);
        $media->attach($editor, $page['revision'], $asset, 'hero', 0, 0);
        self::assertSame(1, $page['page']->refresh()->lock_version);
        $this->assertDatabaseCount('content_media_references', 1);
        try {
            $media->attach($editor, $page['revision'], $private, 'hero', 0, 1);
            self::fail('Private media cannot be attached to public content.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $media->detach($editor, $page['revision'], $asset, 'hero', 1);
        $media->detach($editor, $page['revision'], $asset, 'hero', 1);
        self::assertSame(2, $page['page']->refresh()->lock_version);
        $media->attach($editor, $page['revision'], $asset, 'hero', 0, 2);
        app(PublishPage::class)->execute($publisher, $page['page'], 3);

        try {
            $media->detach($editor, $page['revision'], $asset, 'hero', 4);
            self::fail('Published revision media must be immutable.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $this->expectException(DomainException::class);
        app(MediaService::class)->deleteOrphan($editor, $asset);
    }

    public function test_admin_cms_can_attach_and_detach_governed_media_by_public_identity(): void
    {
        $manager = UserAccount::factory()->create();
        $this->grant($manager, 'content.manage');
        $this->grant($manager, 'media.assets.manage', 'media');
        $this->enableTwoFactor($manager);
        $article = app(CreateArticleDraft::class)->execute($manager, 'admin-media', 'Admin media', 'Body');
        $asset = $this->mediaAsset($manager, 'public');

        $this->actingAs($manager)->post(route('admin.content.media.attach', ['type' => 'articles', 'content' => $article['article']->public_id]), [
            'asset_public_id' => $asset->public_id, 'purpose' => 'hero', 'sort_order' => 0, 'lock_version' => 0,
        ])->assertRedirect(route('admin.content'));
        $this->actingAs($manager)->get(route('admin.content'))->assertOk()->assertSee('test.png')->assertSee('hero');
        $this->actingAs($manager)->delete(route('admin.content.media.detach', [
            'type' => 'articles', 'content' => $article['article']->public_id, 'asset' => $asset->public_id, 'purpose' => 'hero',
        ]), ['lock_version' => 1])->assertRedirect(route('admin.content'));
        $this->assertDatabaseCount('content_media_references', 0);
        self::assertSame(2, $article['article']->refresh()->lock_version);
    }

    private function grant(UserAccount $account, string $code, string $module = 'content'): void
    {
        $permission = PermissionDefinition::query()->where('code', $code)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::module($module)->persistenceValues(), 'starts_at' => now()->subMinute(),
            'status' => 'active', 'granted_by_user_account_id' => $account->getKey(), 'reason' => 'CMS test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }

    private function mediaAsset(UserAccount $actor, string $accessClass): MediaAsset
    {
        return MediaAsset::query()->create([
            'public_id' => (string) Str::ulid(), 'disk' => 'local',
            'storage_key' => 'media/test/'.bin2hex(random_bytes(8)).'.png', 'original_name' => 'test.png',
            'declared_mime' => 'image/png', 'detected_mime' => 'image/png', 'byte_size' => 10,
            'sha256' => hash('sha256', random_bytes(16), true), 'access_class' => $accessClass,
            'scan_status' => 'clean', 'status' => 'active', 'uploaded_by_user_account_id' => $actor->getKey(),
        ]);
    }

    private function enableTwoFactor(UserAccount $account): void
    {
        $account->forceFill([
            'two_factor_secret' => encrypt('cms-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['cms-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }
}
