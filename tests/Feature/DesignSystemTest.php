<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DesignSystemTest extends TestCase
{
    public function test_authentication_surface_renders_the_dark_semantic_theme(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="vi" class="theme-dark">', false)
            ->assertSee('Đăng nhập')
            ->assertSee('bg-canvas', false)
            ->assertDontSee('bg-slate-', false);
    }

    public function test_button_and_feedback_primitives_render_semantic_states(): void
    {
        $this->blade('<x-ui.button type="submit">Lưu</x-ui.button>')
            ->assertSee('type="submit"', false)
            ->assertSee('min-h-11', false)
            ->assertSee('bg-brand', false);

        $this->blade('<x-ui.button icon="shopping-cart" size="sm">Thêm vào giỏ</x-ui.button>')
            ->assertSee('<svg', false)
            ->assertSee('size-4', false)
            ->assertSee('aria-hidden="true"', false)
            ->assertSee('Thêm vào giỏ');

        $this->blade('<x-ui.alert tone="danger" title="Lỗi">Kiểm tra lại</x-ui.alert>')
            ->assertSee('role="alert"', false)
            ->assertSee('<svg', false)
            ->assertSee('Lỗi')
            ->assertSee('Kiểm tra lại');
    }

    public function test_input_has_label_help_required_and_native_control_contract(): void
    {
        $this->blade('<x-ui.input name="email" label="Email" type="email" help="Email công việc" required autocomplete="email" />')
            ->assertSee('for="field-email"', false)
            ->assertSee('id="field-email"', false)
            ->assertSee('aria-describedby="field-email-help"', false)
            ->assertSee('required', false)
            ->assertSee('autocomplete="email"', false);
    }

    public function test_component_templates_use_semantic_colors_and_global_accessibility_controls_exist(): void
    {
        $components = implode("\n", array_map(
            static fn (string $path): string => (string) file_get_contents($path),
            glob(resource_path('views/components/ui/*.blade.php')) ?: [],
        ));
        $css = (string) file_get_contents(resource_path('css/app.css'));

        self::assertDoesNotMatchRegularExpression('/(?:bg|text|border)-(?:slate|gray|zinc|red|blue|cyan|emerald)-\d{2,3}/', $components);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
        self::assertStringContainsString('--color-brand:', $css);
    }
}
