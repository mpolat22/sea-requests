<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\SellerVerificationAiReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Tests\TestCase;

class SellerVerificationAiAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_confidence_ai_review_auto_approves_supplier_verification(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();

        $this->mock(SellerVerificationAiReviewService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('review')->once()->andReturn($this->reviewOutcome('approve'));
        });

        $this->actingAs($seller)
            ->post(route('seller.verification.store'), $this->verificationPayload($category, $subcategory, $port))
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionHas('success.message', 'Your supplier verification was approved automatically after the document review. Your profile is now active.');

        $seller->refresh();

        $this->assertSame('approved', $seller->approval_status);
        $this->assertNotNull($seller->approved_at);
        $this->assertNotNull($seller->seller_verification_submitted_at);
        $this->assertNotNull($seller->seller_verification_ai_reviewed_at);
        $this->assertSame('approve', $seller->seller_verification_ai_review['decision'] ?? null);
        $this->assertNull($seller->seller_rejection_reason);

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($notification->toArray($seller)['title'] ?? null) === 'Application Approved';
            }
        );
    }

    public function test_clear_mismatch_ai_review_auto_rejects_supplier_verification(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();

        $this->mock(SellerVerificationAiReviewService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('review')->once()->andReturn($this->reviewOutcome('reject', [
                'rejection_reason' => 'information_mismatch',
                'rejection_fields' => ['company_registration_documents', 'registration_number'],
                'rejection_note' => 'The company name or registration number extracted from the document does not match the submitted supplier information. Please correct the mismatch and submit again.',
                'analysis' => [
                    'company_name_match' => 'mismatch',
                    'registration_number_match' => 'mismatch',
                    'review_summary' => 'Document details do not match the submitted registration identity.',
                ],
            ]));
        });

        $this->actingAs($seller)
            ->post(route('seller.verification.store'), $this->verificationPayload($category, $subcategory, $port))
            ->assertRedirect(route('seller.verification.create'))
            ->assertSessionHas('error.message', 'Your supplier verification was reviewed automatically, but your registration document still needs correction before activation.');

        $seller->refresh();

        $this->assertSame('rejected', $seller->approval_status);
        $this->assertSame('information_mismatch', $seller->seller_rejection_reason);
        $this->assertSame(['company_registration_documents', 'registration_number'], $seller->seller_rejection_fields);
        $this->assertNotNull($seller->seller_rejected_at);
        $this->assertSame('reject', $seller->seller_verification_ai_review['decision'] ?? null);

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($notification->toArray($seller)['title'] ?? null) === 'Application Rejected';
            }
        );
    }

    public function test_uncertain_ai_review_keeps_supplier_verification_pending_manual_review(): void
    {
        Storage::fake('public');
        Notification::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();

        $this->mock(SellerVerificationAiReviewService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('review')->once()->andReturn($this->reviewOutcome('manual_review', [
                'summary' => 'The document passed basic checks but still needs manual review because the AI result was not confident enough for automatic approval or automatic rejection.',
                'analysis' => [
                    'confidence' => 'medium',
                    'review_summary' => 'The document passed basic checks but still needs manual review because the AI result was not confident enough for automatic approval or automatic rejection.',
                ],
            ]));
        });

        $this->actingAs($seller)
            ->post(route('seller.verification.store'), $this->verificationPayload($category, $subcategory, $port))
            ->assertRedirect(route('approval.pending'))
            ->assertSessionHas('success', 'seller-verification-submitted');

        $seller->refresh();

        $this->assertSame('pending', $seller->approval_status);
        $this->assertNotNull($seller->seller_verification_ai_reviewed_at);
        $this->assertSame('manual_review', $seller->seller_verification_ai_review['decision'] ?? null);
        $this->assertNull($seller->seller_rejection_reason);

        Notification::assertSentTo(
            $seller,
            MarketplaceNotification::class,
            function (MarketplaceNotification $notification, array $channels) use ($seller): bool {
                return in_array('mail', $channels, true)
                    && in_array('database', $channels, true)
                    && ($notification->toArray($seller)['title'] ?? null) === 'Business Application Received';
            }
        );
    }

    public function test_admin_dashboard_business_table_exposes_ai_review_payload(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.test',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'AI Reviewed Supplier',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
            'seller_verification_submitted_at' => now()->subHour(),
            'seller_verification_ai_reviewed_at' => now(),
            'seller_verification_ai_review' => [
                'decision' => 'approve',
                'analysis' => [
                    'confidence' => 'high',
                    'document_type' => 'registration_document',
                    'review_summary' => 'Automatic document verification approved this supplier.',
                ],
            ],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard/Dashboard')
                ->where('businessTable.data.0.company_name', $seller->company_name)
                ->where('businessTable.data.0.seller_verification_ai_review.decision', 'approve')
                ->where('businessTable.data.0.seller_verification_ai_review.analysis.confidence', 'high')
                ->where('businessTable.data.0.seller_verification_ai_reviewed_at', $seller->seller_verification_ai_reviewed_at?->toJSON())
            );
    }

    /**
     * @return array{0: User, 1: Category, 2: Subcategory, 3: Port}
     */
    private function sellerScenario(): array
    {
        $seller = User::factory()->create([
            'role' => 'seller',
            'email_verified_at' => now(),
            'approval_status' => 'pending',
            'approved_at' => null,
            'seller_verification_submitted_at' => null,
            'company_logo_path' => null,
        ]);

        $category = Category::create([
            'name' => 'Calibration & Testing Services',
            'slug' => 'calibration-testing-services',
            'has_subcategories' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $subcategory = Subcategory::create([
            'category_id' => $category->id,
            'name' => 'Pressure Gauge',
            'slug' => 'pressure-gauge',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $port = Port::create([
            'country_code' => 'TR',
            'country_name' => 'Turkey',
            'location_code' => 'IST',
            'port_name' => 'Istanbul',
            'unlocode' => 'TRIST',
            'is_active' => true,
        ]);

        return [$seller, $category, $subcategory, $port];
    }

    private function verificationPayload(Category $category, Subcategory $subcategory, Port $port): array
    {
        return [
            'company_name' => 'Sea Requests Test Supplier',
            'country' => 'Turkey',
            'company_city' => 'Istanbul',
            'company_postal_code' => '34947',
            'company_address_line' => 'Pendik Marina Office Block 2 Istanbul',
            'service_category_ids' => [$category->id],
            'service_subcategory_ids' => [$subcategory->id],
            'service_subcategories_by_category' => [
                (string) $category->id => [$subcategory->id],
            ],
            'service_country_codes' => ['TR'],
            'service_ports_by_country' => [
                'TR' => [$port->id],
            ],
            'phone' => '+90 5550000000',
            'landline_phone' => '5550000000',
            'contact_email' => 'supplier@example.com',
            'company_overview' => 'Marine spare parts and service support.',
            'registration_number' => 'REG-12345',
            'company_logo' => UploadedFile::fake()->image('logo.png'),
            'company_registration_documents' => [
                UploadedFile::fake()->create('company-registration.pdf', 120, 'application/pdf'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reviewOutcome(string $decision, array $overrides = []): array
    {
        $default = [
            'decision' => $decision,
            'rejection_reason' => null,
            'rejection_fields' => [],
            'rejection_note' => null,
            'summary' => match ($decision) {
                'approve' => 'The company registration document was verified with high confidence and the supplier profile passed the automatic verification gate.',
                'reject' => 'The supplier verification was rejected automatically after the document review.',
                default => 'The document passed basic checks but still needs manual review because the AI result was not confident enough for automatic approval or automatic rejection.',
            },
            'review' => [
                'decision' => $decision,
                'reviewed_at' => now()->toISOString(),
                'model' => 'gpt-4o-mini',
                'submitted' => [
                    'company_name' => 'Sea Requests Test Supplier',
                    'registration_number' => 'REG-12345',
                    'country' => 'Turkey',
                    'contact_email' => 'supplier@example.com',
                ],
                'profile_checks' => [
                    'passed' => true,
                    'missing_fields' => [],
                ],
                'documents' => [
                    [
                        'name' => 'company-registration.pdf',
                        'path' => 'seller-verifications/1/company-registration/company-registration.pdf',
                        'mime_type' => 'application/pdf',
                        'size' => 120,
                        'sha256' => 'demo-hash',
                        'source_type' => 'pdf',
                    ],
                ],
                'analysis' => [
                    'confidence' => $decision === 'manual_review' ? 'medium' : 'high',
                    'document_type' => 'registration_document',
                    'quality' => 'clear',
                    'expiry_status' => 'not_shown',
                    'issue_date' => null,
                    'expiry_date' => null,
                    'extracted_company_name' => 'Sea Requests Test Supplier',
                    'extracted_registration_number' => 'REG-12345',
                    'company_name_match' => 'match',
                    'registration_number_match' => 'match',
                    'duplicate_status' => 'clear',
                    'duplicate_matches' => [],
                    'review_summary' => 'Automatic seller verification review completed.',
                    'reasoning' => ['The uploaded document looks like a company registration document.'],
                ],
            ],
        ];

        $outcome = array_replace_recursive($default, $overrides);
        $outcome['decision'] = $decision;
        $outcome['review']['decision'] = $decision;

        return $outcome;
    }
}