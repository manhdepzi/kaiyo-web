<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FoundationBoundaryTest extends TestCase
{
    public function test_v1_contains_only_release_scoped_migrations_and_no_ai_dependency(): void
    {
        $migrationFiles = glob(dirname(__DIR__, 2).'/database/migrations/*.php');

        $migrationNames = array_map('basename', $migrationFiles === false ? [] : $migrationFiles);
        self::assertSame([
            '2026_08_23_000001_create_identity_authentication_tables.php',
            '2026_08_23_000002_create_authorization_tables.php',
            '2026_08_23_000003_create_crm_core_tables.php',
            '2026_08_23_000004_create_catalog_tables.php',
            '2026_08_23_000005_create_pricing_tables.php',
            '2026_08_23_000006_create_inventory_tables.php',
            '2026_08_23_000007_create_media_tables.php',
            '2026_08_23_000008_create_cart_tables.php',
            '2026_08_23_000009_create_checkout_tables.php',
            '2026_08_23_000010_create_order_lifecycle_tables.php',
            '2026_08_23_000011_create_payment_tables.php',
            '2026_08_23_000012_create_shipping_tables.php',
            '2026_08_23_000013_create_quotation_tables.php',
            '2026_08_23_000014_create_quote_to_order_conversion.php',
            '2026_08_23_000015_create_cms_page_tables.php',
            '2026_08_23_000016_create_cms_content_type_tables.php',
            '2026_08_23_000017_create_growth_delivery_tables.php',
            '2026_08_23_000018_create_dispatch_records_table.php',
            '2026_08_28_000019_add_presentation_to_banner_revisions.php',
            '2026_08_28_000020_create_notifications_tables.php',
            '2026_08_29_000021_create_public_contact_submissions.php',
        ], $migrationNames);

        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $packages = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);
        self::assertArrayNotHasKey('openai-php/client', $packages);
    }

    public function test_no_legacy_global_model_namespace_is_introduced(): void
    {
        $modelDirectory = dirname(__DIR__, 2).'/app/Models';

        if (! is_dir($modelDirectory)) {
            self::assertDirectoryDoesNotExist($modelDirectory);

            return;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelDirectory));
        $phpFiles = [];

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }

        self::assertSame([], $phpFiles);
    }
}
