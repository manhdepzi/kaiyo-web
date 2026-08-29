<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\CMS\Application\Support\ProjectPortfolioCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProjectPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_legacy_project_images_are_local_and_rendered_in_the_portfolio(): void
    {
        $projects = app(ProjectPortfolioCatalog::class)->all();

        self::assertCount(42, $projects);
        self::assertCount(42, array_unique(array_map(fn ($project): string => $project->slug, $projects)));
        foreach ($projects as $project) {
            self::assertFileExists(public_path(ltrim($project->imagePath, '/')));
        }

        $response = $this->get('/du-an')->assertOk()
            ->assertSee('Dự án Kaiyo đã thực hiện')
            ->assertSee('Nhà máy Seojin Bắc Ninh')
            ->assertSee('Hệ thống hút khí nóng cho máy CNC')
            ->assertDontSee('giacongonggio.net', false);

        self::assertSame(42, substr_count($response->getContent(), 'data-project-card'));
    }

    public function test_project_portfolio_is_linked_from_navigation_and_sitemap(): void
    {
        $this->get('/')->assertOk()->assertSee(route('public.projects'));
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('public.projects'));
    }
}
