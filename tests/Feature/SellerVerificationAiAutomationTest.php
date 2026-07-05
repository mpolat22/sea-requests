<?php

namespace Tests\Feature;

use App\Jobs\RunSellerVerificationAiReviewJob;
use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Support\SellerVerificationAiAutomationService;
use App\Support\SellerVerificationAiReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Mockery\MockInterface;
use Tests\TestCase;

class SellerVerificationAiAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_submission_stays_pending_and_queues_delayed_ai_review(): void
    {
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();

        $this->actingAs($seller)
            ->post(route('seller.verification.store'), $this->verificationPayload($category, $subcategory, $port))
            ->assertRedirect(route('approval.pending'))
            ->assertSessionHas('success', 'seller-verification-submitted');

        $seller->refresh();

        $this->assertSame('pending', $seller->approval_status);
        $this->assertNotNull($seller->seller_verification_submitted_at);
        $this->assertNull($seller->seller_verification_ai_review);
        $this->assertNull($seller->seller_verification_ai_reviewed_at);
        $this->assertNull($seller->seller_rejection_reason);

        Queue::assertPushed(RunSellerVerificationAiReviewJob::class, function (RunSellerVerificationAiReviewJob $job) use ($seller): bool {
            return $job->userId === $seller->id
                && $job->submittedAtIso === $seller->seller_verification_submitted_at?->toISOString()
                && $job->delay !== null;
        });

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

    public function test_delayed_ai_review_auto_approves_if_submission_is_still_pending(): void
    {
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();
        $submittedAtIso = $this->submitVerification($seller, $category, $subcategory, $port);

        $this->travel(61)->minutes();

        $this->mock(SellerVerificationAiReviewService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('review')->once()->andReturn($this->reviewOutcome('approve'));
        });

        app(SellerVerificationAiAutomationService::class)->processIfStillPending($seller->id, $submittedAtIso);

        $seller->refresh();

        $this->assertSame('approved', $seller->approval_status);
        $this->assertNotNull($seller->approved_at);
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

    public function test_delayed_ai_review_auto_rejects_if_submission_is_still_pending(): void
    {
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();
        $submittedAtIso = $this->submitVerification($seller, $category, $subcategory, $port);

        $this->travel(61)->minutes();

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

        app(SellerVerificationAiAutomationService::class)->processIfStillPending($seller->id, $submittedAtIso);

        $seller->refresh();

        $this->assertSame('rejected', $seller->approval_status);
        $this->assertSame('information_mismatch', $seller->seller_rejection_reason);
        $this->assertSame(['company_registration_documents', 'registration_number'], $seller->seller_rejection_fields);
        $this->assertNotNull($seller->seller_rejected_at);
        $this->assertNotNull($seller->seller_verification_ai_reviewed_at);
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

    public function test_delayed_ai_review_keeps_submission_pending_for_manual_review(): void
    {
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();
        $submittedAtIso = $this->submitVerification($seller, $category, $subcategory, $port);

        $this->travel(61)->minutes();

        $this->mock(SellerVerificationAiReviewService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('review')->once()->andReturn($this->reviewOutcome('manual_review', [
                'summary' => 'The document passed basic checks but still needs manual review because the AI result was not confident enough for automatic approval or automatic rejection.',
                'analysis' => [
                    'confidence' => 'medium',
                    'review_summary' => 'The document passed basic checks but still needs manual review because the AI result was not confident enough for automatic approval or automatic rejection.',
                ],
            ]));
        });

        app(SellerVerificationAiAutomationService::class)->processIfStillPending($seller->id, $submittedAtIso);

        $seller->refresh();

        $this->assertSame('pending', $seller->approval_status);
        $this->assertNotNull($seller->seller_verification_ai_reviewed_at);
        $this->assertSame('manual_review', $seller->seller_verification_ai_review['decision'] ?? null);
        $this->assertNull($seller->seller_rejection_reason);
    }

    public function test_delayed_ai_review_skips_when_admin_already_approved_submission(): void
    {
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        [$seller, $category, $subcategory, $port] = $this->sellerScenario();
        $submittedAtIso = $this->submitVerification($seller, $category, $subcategory, $port);

        $seller->forceFill([
            'approval_status' => 'approved',
            'approved_at' => now(),
        ])->save();

        $this->travel(61)->minutes();

        $this->mock(SellerVerificationAiReviewService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('review');
        });

        $result = app(SellerVerificationAiAutomationService::class)->processIfStillPending($seller->id, $submittedAtIso);

        $seller->refresh();

        $this->assertNull($result);
        $this->assertSame('approved', $seller->approval_status);
        $this->assertNull($seller->seller_verification_ai_reviewed_at);
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

    private function submitVerification(User $seller, Category $category, Subcategory $subcategory, Port $port): string
    {
        $this->actingAs($seller)
            ->post(route('seller.verification.store'), $this->verificationPayload($category, $subcategory, $port))
            ->assertRedirect(route('approval.pending'));

        $seller->refresh();

        return $seller->seller_verification_submitted_at?->toISOString() ?? '';
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
                'approve' => 'Automatic document verification approved this supplier.',
                'reject' => 'Automatic document verification found a clear mismatch in the submitted registration document.',
                default => 'The supplier verification remains pending for manual review.',
            },
            'review' => [
                'decision' => $decision,
                'submitted' => [
                    'company_name' => 'Sea Requests Test Supplier',
                    'registration_number' => 'REG-12345',
                ],
                'analysis' => [
                    'confidence' => $decision === 'approve' ? 'high' : ($decision === 'reject' ? 'high' : 'medium'),
                    'document_type' => 'registration_document',
                    'quality' => 'clear',
                    'expiry_status' => 'not_found',
                    'company_name_match' => $decision === 'reject' ? 'mismatch' : 'match',
                    'registration_number_match' => $decision === 'reject' ? 'mismatch' : 'match',
                    'duplicate_status' => 'unique',
                    'extracted_company_name' => 'Sea Requests Test Supplier',
                    'extracted_registration_number' => 'REG-12345',
                    'review_summary' => match ($decision) {
                        'approve' => 'The uploaded company registration document clearly matches the submitted business identity.',
                        'reject' => 'The uploaded company registration document does not match the submitted business identity.',
                        default => 'The uploaded document needs manual review because the AI result is not confident enough for an automatic decision.',
                    },
                    'reasoning' => [
                        'Registration document was readable.',
                        'Company name and registration number were reviewed.',
                    ],
                ],
            ],
        ];

        return array_replace_recursive($default, $overrides);
    }
}
