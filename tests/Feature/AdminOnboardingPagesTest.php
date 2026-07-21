<?php

namespace Tests\Feature;

use App\Models\OutreachContact;
use App\Models\User;
use App\Notifications\PreRegisteredAccountCompletionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminOnboardingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_onboarding_workspace(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.onboarding'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Onboarding/Index')
                ->where('activeTab', 'onboarding')
                ->where('dashboard.navigation.onboarding_count', 0)
            );
    }

    public function test_admin_can_create_supplier_onboarding_account_and_send_completion_email_automatically(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.manual.store'), [
                'audience' => 'seller',
                'company_name' => 'Anchor Industries (Pty) Ltd',
                'contact_email' => 'sales@anchors.co.za',
                'phone' => '+27215310525',
                'website_url' => 'www.anchors.co.za',
                'country' => 'South Africa',
                'city' => 'Cape Town',
                'postal_code' => '7405',
                'address' => 'Old Mill Road 20',
                'business_activity' => 'Marine equipment suppliers and ship chandlers.',
                'serviced_ports' => "Cape Town / South Africa
Durban / South Africa",
                'company_overview' => 'Anchor Industries offers engineering services and equipment for lifting, rigging, marine and offshore mooring.',
            ])
            ->assertRedirect();

        $contact = OutreachContact::query()->where('email', 'sales@anchors.co.za')->firstOrFail();

        $user = User::query()->where('email', 'sales@anchors.co.za')->firstOrFail();

        $this->assertTrue($user->isSeller());
        $this->assertSame('pending', $user->approval_status);
        $this->assertSame('Anchor Industries (Pty) Ltd', $contact->organization_name);
        $this->assertSame('Cape Town', data_get($contact->source_payload, 'parsed.city'));
        $this->assertSame('Cape Town', data_get($contact->source_payload, 'parsed.serviced_ports.0.port'));
        $this->assertSame('South Africa', data_get($contact->source_payload, 'parsed.serviced_ports.0.country'));

        $user->refresh();
        $contact->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->seller_verification_onboarding_sent_at);
        $this->assertSame('email_sent', data_get($contact->source_payload, 'onboarding_status'));

        Notification::assertSentTo($user, PreRegisteredAccountCompletionNotification::class);
    }


    public function test_admin_can_create_manual_ready_onboarding_profile(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.manual.store'), [
                'audience' => 'seller',
                'company_name' => 'Manual Marine Supply Ltd',
                'email' => 'manual@example.test',
                'phone' => '+905550000000',
                'website_url' => 'www.manual-marine.example',
                'country' => 'Turkey',
                'city' => 'Istanbul',
                'postal_code' => '34900',
                'address' => 'Tuzla Shipyard Area',
                'business_activity' => 'Marine equipment suppliers and ship chandlers.',
                'serviced_ports' => "Istanbul / Turkey
Izmir / Turkey",
                'company_overview' => 'Manual Marine Supply Ltd supports ship owners with marine equipment and spare parts.',
            ])
            ->assertRedirect();

        $contact = OutreachContact::query()->where('email', 'manual@example.test')->firstOrFail();

        $user = User::query()->where('email', 'manual@example.test')->firstOrFail();

        $this->assertSame('email_sent', data_get($contact->source_payload, 'onboarding_status'));
        $this->assertSame('manual_profile', data_get($contact->source_payload, 'created_from'));
        $this->assertSame('Manual Marine Supply Ltd', $contact->organization_name);
        $this->assertSame('Turkey', data_get($contact->source_payload, 'parsed.country'));
        $this->assertSame('Istanbul', data_get($contact->source_payload, 'parsed.serviced_ports.0.port'));
        $this->assertSame('Turkey', data_get($contact->source_payload, 'parsed.serviced_ports.0.country'));
        $this->assertNotNull($user->email_verified_at);

        Notification::assertSentTo($user, PreRegisteredAccountCompletionNotification::class);
    }

    public function test_non_admin_cannot_open_onboarding_workspace(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer']);

        $this->actingAs($buyer)
            ->get(route('admin.onboarding'))
            ->assertForbidden();
    }
}
