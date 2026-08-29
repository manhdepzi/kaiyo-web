<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class PublicContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('crm:public-contact:test');
    }

    public function test_contact_page_exposes_server_validated_crm_form(): void
    {
        $this->get('/lien-he')
            ->assertOk()
            ->assertSee('Gửi yêu cầu')
            ->assertSee('privacy_accepted', false)
            ->assertSee('operation_key', false);
    }

    public function test_contact_submission_is_normalized_and_idempotent(): void
    {
        $payload = [
            'name' => 'Nguyễn Văn An',
            'company_name' => 'Công ty An Phát',
            'email' => 'SALES@EXAMPLE.TEST',
            'phone' => '0395 216 869',
            'topic' => 'project',
            'message' => 'Tôi cần trao đổi cấu hình ống gió cho một dự án mới.',
            'operation_key' => 'contact-operation-001',
            'privacy_accepted' => '1',
            'website' => '',
        ];

        $this->post('/lien-he', $payload)->assertRedirect('/lien-he')->assertSessionHas('status');
        $this->post('/lien-he', $payload)->assertRedirect('/lien-he')->assertSessionHas('status');

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('public_contact_submissions', 1);
        $this->assertDatabaseHas('leads', [
            'source' => 'public_contact',
            'display_name' => 'Nguyễn Văn An',
            'email_normalized' => 'sales@example.test',
            'phone_e164' => '+84395216869',
            'status' => 'new',
            'owner_user_account_id' => null,
        ]);
        $this->assertDatabaseHas('public_contact_submissions', [
            'topic' => 'project',
            'message' => 'Tôi cần trao đổi cấu hình ống gió cho một dự án mới.',
        ]);
    }

    public function test_contact_rejects_missing_identity_consent_and_honeypot(): void
    {
        $this->from('/lien-he')->post('/lien-he', [
            'name' => 'Nguyễn Văn An',
            'topic' => 'product',
            'message' => 'Tôi cần được tư vấn thêm về sản phẩm ống gió.',
            'operation_key' => 'contact-operation-invalid',
            'website' => 'spam.example',
        ])->assertRedirect('/lien-he')->assertSessionHasErrors(['email', 'phone', 'privacy_accepted', 'website']);

        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('public_contact_submissions', 0);
    }
}
