<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportOutreachContactsJob;
use App\Mail\OutreachSenderTestMail;
use App\Models\OutreachContact;
use App\Models\OutreachImportRun;
use App\Models\OutreachSchedule;
use App\Models\OutreachSegment;
use App\Models\OutreachSendLog;
use App\Models\OutreachSenderAccount;
use App\Models\OutreachTemplate;
use App\Models\User;
use App\Support\AdminDashboardData;
use App\Support\Outreach\OutreachDynamicMailer;
use App\Support\Outreach\OutreachRegionManager;
use App\Support\Outreach\OutreachSenderAccountManager;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminOutreachController extends Controller
{
    public function index(Request $request, AdminDashboardData $dashboardData, OutreachRegionManager $regions): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $contactStatusFilter = (string) $request->string('contacts_status', 'all');
        $contactRegionFilter = (string) $request->string('contacts_region', '');
        $contactSearchFilter = trim((string) $request->string('contacts_search', ''));
        $contactPage = max(1, (int) $request->integer('contacts_page', 1));

        $senderAccounts = OutreachSenderAccount::query()
            ->where('audience', 'supplier')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (OutreachSenderAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'from_name' => $account->from_name,
                'from_email' => $account->from_email,
                'reply_to_email' => $account->reply_to_email,
                'smtp_host' => $account->smtp_host,
                'smtp_port' => $account->smtp_port,
                'smtp_encryption' => $account->smtp_encryption,
                'smtp_username' => $account->smtp_username,
                'is_active' => $account->is_active,
                'is_default' => $account->is_default,
                'created_at' => optional($account->created_at)->toIso8601String(),
                'has_password' => filled($account->smtp_password),
                'update_url' => route('admin.outreach.senders.update', $account),
                'delete_url' => route('admin.outreach.senders.destroy', $account),
                'test_url' => route('admin.outreach.senders.test', $account),
            ])
            ->values();

        $templates = OutreachTemplate::query()
            ->where('audience', 'supplier')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (OutreachTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'body_text' => $template->body_text,
                'is_active' => $template->is_active,
                'sort_order' => $template->sort_order,
                'created_at' => optional($template->created_at)->toIso8601String(),
                'update_url' => route('admin.outreach.templates.update', $template),
                'delete_url' => route('admin.outreach.templates.destroy', $template),
            ])
            ->values();

        $canonicalNames = array_values($regions->canonicalSegmentNames());

        $canonicalSegments = OutreachSegment::query()
            ->where('audience', 'supplier')
            ->whereIn('name', $canonicalNames)
            ->withCount([
                'primaryContacts as contacts_count' => fn ($query) => $query->where('audience', 'supplier'),
            ])
            ->with(['schedule'])
            ->get()
            ->keyBy(fn (OutreachSegment $segment) => (string) ($segment->region_key ?: 'global'));

        $contactSummary = OutreachContact::query()
            ->where('audience', 'supplier')
            ->selectRaw('COUNT(*) as total_contacts')
            ->selectRaw(
                "SUM(CASE WHEN status = '".OutreachContact::STATUS_ACTIVE."' THEN 1 ELSE 0 END) as active_contacts"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = '".OutreachContact::STATUS_REGISTERED."' THEN 1 ELSE 0 END) as registered_contacts"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = '".OutreachContact::STATUS_UNSUBSCRIBED."' THEN 1 ELSE 0 END) as unsubscribed_contacts"
            )
            ->first();

        $contactsByRegion = OutreachContact::query()
            ->leftJoin('outreach_segments as primary_segments', 'primary_segments.id', '=', 'outreach_contacts.primary_segment_id')
            ->where('outreach_contacts.audience', 'supplier')
            ->selectRaw("COALESCE(primary_segments.region_key, 'global') as region_key")
            ->selectRaw('COUNT(*) as contacts_count')
            ->selectRaw(
                "SUM(CASE WHEN outreach_contacts.status = '".OutreachContact::STATUS_ACTIVE."' THEN 1 ELSE 0 END) as active_contacts"
            )
            ->selectRaw(
                "SUM(CASE WHEN outreach_contacts.status = '".OutreachContact::STATUS_REGISTERED."' THEN 1 ELSE 0 END) as registered_contacts"
            )
            ->selectRaw(
                "SUM(CASE WHEN outreach_contacts.status = '".OutreachContact::STATUS_UNSUBSCRIBED."' THEN 1 ELSE 0 END) as unsubscribed_contacts"
            )
            ->selectRaw(
                "SUM(CASE WHEN outreach_contacts.status = '".OutreachContact::STATUS_REPLIED."' THEN 1 ELSE 0 END) as replied_contacts"
            )
            ->selectRaw(
                "SUM(CASE WHEN outreach_contacts.status = '".OutreachContact::STATUS_PAUSED."' THEN 1 ELSE 0 END) as paused_contacts"
            )
            ->groupBy(DB::raw("COALESCE(primary_segments.region_key, 'global')"))
            ->get()
            ->keyBy(fn ($row) => (string) ($row->region_key ?: 'global'));

        $regionCards = collect($regions->regionOptions())
            ->map(function (array $region) use ($regions, $canonicalSegments, $contactsByRegion) {
                $regionKey = $region['key'];
                $canonicalSegment = $canonicalSegments->get($regionKey);
                $regionContacts = $contactsByRegion->get($regionKey);

                if (! $canonicalSegment && ! $regionContacts) {
                    return null;
                }

                $schedule = $canonicalSegment?->schedule;
                $contactsCount = (int) ($regionContacts?->contacts_count ?? $canonicalSegment?->contacts_count ?? 0);
                $weeklyPlan = $regions->normalizeWeeklyPlan(
                    $regionKey,
                    data_get($schedule?->meta, 'weekly_plan', []),
                    $contactsCount,
                    (int) ($schedule?->send_interval_minutes ?? 1),
                );

                return [
                    'key' => $regionKey,
                    'label' => $region['label'],
                    'canonical_segment_name' => $regions->canonicalSegmentName($regionKey),
                    'contacts_count' => $contactsCount,
                    'active_contacts' => (int) ($regionContacts?->active_contacts ?? 0),
                    'registered_contacts' => (int) ($regionContacts?->registered_contacts ?? 0),
                    'unsubscribed_contacts' => (int) ($regionContacts?->unsubscribed_contacts ?? 0),
                    'replied_contacts' => (int) ($regionContacts?->replied_contacts ?? 0),
                    'paused_contacts' => (int) ($regionContacts?->paused_contacts ?? 0),
                'schedule' => [
                    'starts_on' => optional($schedule?->starts_on)->toDateString() ?: now()->toDateString(),
                    'send_interval_minutes' => (int) ($schedule?->send_interval_minutes ?? 1),
                    'is_active' => (bool) ($schedule?->is_active ?? false),
                    'weekly_plan' => $weeklyPlan,
                    'last_dispatched_at' => optional($schedule?->last_dispatched_at)->toIso8601String(),
                ],
                    'plan_update_url' => route('admin.outreach.regions.update', ['regionKey' => $regionKey]),
                    'plan_delete_url' => route('admin.outreach.regions.destroy', ['regionKey' => $regionKey]),
                ];
            })
            ->filter()
            ->values();

        $logs = OutreachSendLog::query()
            ->with(['segment:id,name', 'template:id,name', 'senderAccount:id,name,from_email'])
            ->latest('id')
            ->limit(120)
            ->get()
            ->map(fn (OutreachSendLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'recipient_email' => $log->recipient_email,
                'recipient_organization' => $log->recipient_organization,
                'segment_name' => $log->segment?->name,
                'template_name' => $log->template?->name,
                'sender_name' => $log->senderAccount?->from_name ?: $log->senderAccount?->name,
                'sender_email' => $log->sender_email ?: $log->senderAccount?->from_email,
                'subject' => $log->subject,
                'queued_at' => optional($log->queued_at)->toIso8601String(),
                'attempted_at' => optional($log->attempted_at)->toIso8601String(),
                'sent_at' => optional($log->sent_at)->toIso8601String(),
                'error_message' => $log->error_message,
            ])
            ->values();

        $imports = OutreachImportRun::query()
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (OutreachImportRun $run) => [
                'id' => $run->id,
                'file_name' => $run->file_name,
                'status' => $run->status,
                'row_count' => $run->row_count,
                'processed_count' => $run->processed_count,
                'new_contacts_count' => $run->new_contacts_count,
                'updated_contacts_count' => $run->updated_contacts_count,
                'duplicate_emails_count' => $run->duplicate_emails_count,
                'skipped_count' => $run->skipped_count,
                'message' => $run->message,
                'created_at' => optional($run->created_at)->toIso8601String(),
                'completed_at' => optional($run->completed_at)->toIso8601String(),
            ])
            ->values();

        $contactsQuery = OutreachContact::query()
            ->with(['primarySegment:id,name,region_key'])
            ->where('audience', 'supplier');

        if (in_array($contactStatusFilter, [
            OutreachContact::STATUS_ACTIVE,
            OutreachContact::STATUS_REGISTERED,
            OutreachContact::STATUS_UNSUBSCRIBED,
            OutreachContact::STATUS_REPLIED,
            OutreachContact::STATUS_PAUSED,
        ], true)) {
            $contactsQuery->where('status', $contactStatusFilter);
        } else {
            $contactStatusFilter = 'all';
        }

        if (filled($contactRegionFilter) && in_array($contactRegionFilter, $regions->regionKeys(), true)) {
            $contactsQuery->whereHas('primarySegment', fn ($query) => $query->where('region_key', $contactRegionFilter));
        } else {
            $contactRegionFilter = '';
        }

        if ($contactSearchFilter !== '') {
            $searchTerm = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $contactSearchFilter).'%';

            $contactsQuery->where(function ($query) use ($searchTerm) {
                $query->where('email', 'like', $searchTerm)
                    ->orWhere('organization_name', 'like', $searchTerm)
                    ->orWhere('source_name', 'like', $searchTerm);
            });
        }

        $contactsPage = $contactsQuery
            ->orderByRaw(
                "case status
                    when '".OutreachContact::STATUS_ACTIVE."' then 0
                    when '".OutreachContact::STATUS_REGISTERED."' then 1
                    when '".OutreachContact::STATUS_UNSUBSCRIBED."' then 2
                    when '".OutreachContact::STATUS_REPLIED."' then 3
                    when '".OutreachContact::STATUS_PAUSED."' then 4
                    else 5
                end"
            )
            ->orderBy('organization_name')
            ->orderBy('email')
            ->paginate(15, ['*'], 'contacts_page', $contactPage);

        return Inertia::render('Admin/Outreach/Index', [
            'dashboard' => $dashboardData->dashboard(),
            'activeTab' => 'outreach',
            'summary' => [
                'total_contacts' => (int) ($contactSummary?->total_contacts ?? 0),
                'active_contacts' => (int) ($contactSummary?->active_contacts ?? 0),
                'registered_contacts' => (int) ($contactSummary?->registered_contacts ?? 0),
                'unsubscribed_contacts' => (int) ($contactSummary?->unsubscribed_contacts ?? 0),
                'regions_count' => $regionCards->count(),
                'active_schedules' => $regionCards->filter(fn (array $region) => (bool) data_get($region, 'schedule.is_active'))->count(),
                'sent_today' => OutreachSendLog::query()->whereDate('sent_at', Carbon::today('Europe/Istanbul'))->where('status', OutreachSendLog::STATUS_SENT)->count(),
                'queued_now' => OutreachSendLog::query()->where('status', OutreachSendLog::STATUS_QUEUED)->count(),
            ],
            'regions' => $regionCards,
            'regionOptions' => $regions->regionOptions(),
            'senderAccounts' => $senderAccounts,
            'templates' => $templates,
            'logs' => $logs,
            'contactsPage' => $this->transformContactsPage($contactsPage, $regions, $contactStatusFilter, $contactRegionFilter, $contactSearchFilter),
            'imports' => $imports,
            'urls' => [
                'index' => route('admin.outreach'),
                'import' => route('admin.outreach.imports.store'),
                'template_store' => route('admin.outreach.templates.store'),
                'contact_store' => route('admin.outreach.contacts.store'),
                'sender_store' => route('admin.outreach.senders.store'),
                'sender_test_draft' => route('admin.outreach.senders.test-draft'),
            ],
            'weekdayOptions' => $regions->weekdayOptions(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $file = $validated['file'];
        $disk = Storage::disk('local');
        $directory = $disk->path('outreach-imports');
        File::ensureDirectoryExists($directory, 0770, true);
        @chmod($directory, 0770);

        $storedPath = $file->storeAs('outreach-imports', now()->format('YmdHis').'-'.$file->getClientOriginalName(), 'local');

        if ($storedPath !== false) {
            @chmod($disk->path($storedPath), 0660);
        }

        $run = OutreachImportRun::query()->create([
            'audience' => 'supplier',
            'file_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'status' => 'queued',
            'imported_by' => $request->user()?->id,
            'message' => 'Import queued on the isolated outreach worker.',
        ]);

        ImportOutreachContactsJob::dispatch($run->id)->onQueue('outreach');

        return back()->with('success', [
            'message' => 'Supplier CSV import queued. The outreach worker will process it in the background.',
        ]);
    }

    public function storeManualContact(Request $request, OutreachRegionManager $regions): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'organization_name' => ['required', 'string', 'max:255'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'region_key' => ['required', Rule::in($regions->regionKeys())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $normalizedEmail = mb_strtolower(trim((string) $validated['email']));
        $regionKey = (string) $validated['region_key'];
        $canonicalSegment = $regions->ensureCanonicalSegment($regionKey);
        $isRegistered = User::query()
            ->where('role', 'seller')
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->exists();

        $contact = OutreachContact::query()->firstOrNew(['email' => $normalizedEmail]);
        $existingPayload = is_array($contact->source_payload) ? $contact->source_payload : [];

        $contact->audience = 'supplier';
        $contact->primary_segment_id = $canonicalSegment->id;
        $contact->organization_name = trim((string) $validated['organization_name']);
        $contact->source_name = trim((string) ($validated['source_name'] ?? '')) ?: $contact->source_name;
        $contact->notes = trim((string) ($validated['notes'] ?? '')) ?: $contact->notes;
        $contact->source_payload = [
            ...$existingPayload,
            'label' => $canonicalSegment->name,
            'region_key' => $regionKey,
            'source_type' => 'manual',
        ];

        if ($contact->status !== OutreachContact::STATUS_UNSUBSCRIBED) {
            $contact->status = $isRegistered
                ? OutreachContact::STATUS_REGISTERED
                : ($contact->status === OutreachContact::STATUS_REPLIED ? OutreachContact::STATUS_REPLIED : OutreachContact::STATUS_ACTIVE);
        }

        $contact->save();
        $contact->segments()->syncWithoutDetaching([$canonicalSegment->id]);

        $segmentWithCount = $canonicalSegment->fresh()->loadCount([
            'primaryContacts as contacts_count' => fn ($query) => $query->where('audience', 'supplier'),
        ]);

        $regions->syncCanonicalSchedule(
            $segmentWithCount,
            (int) ($segmentWithCount->contacts_count ?? 0),
        );

        return back()->with('success', [
            'message' => $contact->wasRecentlyCreated
                ? 'Supplier outreach contact added to '.$regions->regionLabel($regionKey).'.'
                : 'Supplier outreach contact updated in '.$regions->regionLabel($regionKey).'.',
        ]);
    }

    public function destroyContact(Request $request, OutreachContact $contact, OutreachRegionManager $regions): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($contact->audience === 'supplier', 404);

        $regionKey = (string) ($contact->primarySegment?->region_key ?: 'global');
        $regionLabel = $regions->regionLabel($regionKey);

        $contact->delete();

        return back()->with('success', [
            'message' => 'Supplier outreach contact deleted from '.$regionLabel.'.',
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateTemplate($request);

        OutreachTemplate::query()->create([
            ...$validated,
            'audience' => 'supplier',
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', [
            'message' => 'Outreach mail template created.',
        ]);
    }

    public function storeSenderAccount(Request $request, OutreachSenderAccountManager $senderAccounts): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateSenderAccount($request);

        $senderAccounts->store([
            ...$validated,
            'audience' => 'supplier',
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', [
            'message' => 'Outreach sender account created.',
        ]);
    }

    public function updateSenderAccount(Request $request, OutreachSenderAccount $sender, OutreachSenderAccountManager $senderAccounts): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($sender->audience === 'supplier', 404);

        $validated = $this->validateSenderAccount($request, $sender);

        $senderAccounts->update($sender, [
            ...$validated,
            'audience' => 'supplier',
        ]);

        return back()->with('success', [
            'message' => 'Outreach sender account updated.',
        ]);
    }

    public function testDraftSenderAccount(Request $request, OutreachDynamicMailer $mailer): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateSenderAccount($request);

        $sender = new OutreachSenderAccount([
            ...$validated,
            'audience' => 'supplier',
        ]);

        return $this->sendSenderTestMail($request, $sender, $mailer);
    }

    public function testSenderAccount(Request $request, OutreachSenderAccount $sender, OutreachDynamicMailer $mailer): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($sender->audience === 'supplier', 404);

        return $this->sendSenderTestMail($request, $sender, $mailer);
    }

    public function destroySenderAccount(Request $request, OutreachSenderAccount $sender, OutreachSenderAccountManager $senderAccounts): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($sender->audience === 'supplier', 404);

        if (! $senderAccounts->canDelete($sender)) {
            return back()->withErrors([
                'sender' => 'This sender account still has queued outreach emails. Wait until they finish or reactivate another sender first.',
            ]);
        }

        $senderAccounts->destroy($sender);

        return back()->with('success', [
            'message' => 'Outreach sender account deleted.',
        ]);
    }

    public function updateTemplate(Request $request, OutreachTemplate $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($template->audience === 'supplier', 404);

        $template->update($this->validateTemplate($request));

        return back()->with('success', [
            'message' => 'Outreach mail template updated.',
        ]);
    }

    public function destroyTemplate(Request $request, OutreachTemplate $template): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($template->audience === 'supplier', 404);

        $template->delete();

        return back()->with('success', [
            'message' => 'Outreach mail template deleted.',
        ]);
    }

    public function updateRegionPlan(Request $request, string $regionKey, OutreachRegionManager $regions): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(in_array($regionKey, $regions->regionKeys(), true), 404);

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'send_interval_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'weekly_plan' => ['required', 'array', 'size:7'],
            'weekly_plan.*.weekday' => ['required', 'integer', 'between:1,7'],
            'weekly_plan.*.enabled' => ['required', 'boolean'],
            'weekly_plan.*.start_time' => ['nullable', 'date_format:H:i'],
            'weekly_plan.*.end_time' => ['nullable', 'date_format:H:i'],
            'weekly_plan.*.daily_limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);
        $weeklyPlan = $regions->normalizeWeeklyPlan(
            $regionKey,
            $validated['weekly_plan'],
            0,
            (int) $validated['send_interval_minutes'],
        );

        $enabledDays = collect($weeklyPlan)->filter(fn (array $day) => (bool) ($day['enabled'] ?? false))->values();

        if ($validated['is_active'] && $enabledDays->isEmpty()) {
            return back()->withErrors([
                'weekly_plan' => 'Enable at least one weekday before activating this region plan.',
            ]);
        }

        if ($validated['is_active']) {
            $hasActiveTemplate = OutreachTemplate::query()
                ->where('audience', 'supplier')
                ->where('is_active', true)
                ->exists();

            if (! $hasActiveTemplate) {
                return back()->withErrors([
                    'templates' => 'Create at least one active template before activating this region plan.',
                ]);
            }
        }

        foreach ($enabledDays as $day) {
            if (! filled($day['start_time'] ?? null) || ! filled($day['end_time'] ?? null) || ! filled($day['daily_limit'] ?? null)) {
                return back()->withErrors([
                    'weekly_plan' => 'Every active weekday needs start time, end time, and daily send limit.',
                ]);
            }
        }

        $segment = $regions->ensureCanonicalSegment($regionKey);
        $contactCount = OutreachContact::query()
            ->where('audience', 'supplier')
            ->where('primary_segment_id', $segment->id)
            ->count();

        $schedule = $regions->syncCanonicalSchedule($segment, $contactCount);
        $firstEnabledDay = $enabledDays->first();

        $schedule->starts_on = $validated['starts_on'];
        $schedule->recurrence = 'weekly';
        $schedule->weekday = (int) ($firstEnabledDay['weekday'] ?? $schedule->weekday);
        $schedule->send_interval_minutes = (int) $validated['send_interval_minutes'];
        $schedule->is_active = (bool) $validated['is_active'];
        $schedule->uses_recommended_window = false;
        $schedule->start_time = $firstEnabledDay['start_time'] ?? $schedule->start_time;
        $schedule->end_time = $firstEnabledDay['end_time'] ?? $schedule->end_time;
        $schedule->template_rotation = [];
        $schedule->meta = [
            ...(is_array($schedule->meta) ? $schedule->meta : []),
            'weekly_plan' => $regions->normalizeWeeklyPlan(
                $regionKey,
                $weeklyPlan,
                $contactCount,
                (int) $validated['send_interval_minutes'],
            ),
        ];
        $schedule->save();

        return back()->with('success', [
            'message' => $regions->regionLabel($regionKey).' outreach plan updated.',
        ]);
    }

    public function destroyRegionPlan(Request $request, string $regionKey, OutreachRegionManager $regions): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(in_array($regionKey, $regions->regionKeys(), true), 404);

        $segment = $regions->ensureCanonicalSegment($regionKey);
        $schedule = OutreachSchedule::query()->firstOrNew(['segment_id' => $segment->id]);
        $weeklyPlan = collect($regions->defaultWeeklyPlan($regionKey))
            ->map(fn (array $day) => [
                ...$day,
                'enabled' => false,
                'start_time' => null,
                'end_time' => null,
                'daily_limit' => null,
            ])
            ->values()
            ->all();

        $recommendation = app(\App\Support\Outreach\OutreachLabelParser::class)->recommendation($regionKey, 0);

        $schedule->audience = 'supplier';
        $schedule->recurrence = 'weekly';
        $schedule->starts_on = now()->toDateString();
        $schedule->weekday = (int) ($recommendation['weekday'] ?? 1);
        $schedule->suggested_start_time = $recommendation['start_time'] ?? '09:00';
        $schedule->suggested_end_time = $recommendation['end_time'] ?? '11:00';
        $schedule->uses_recommended_window = false;
        $schedule->start_time = $recommendation['start_time'] ?? '09:00';
        $schedule->end_time = $recommendation['end_time'] ?? '11:00';
        $schedule->send_interval_minutes = 1;
        $schedule->is_active = false;
        $schedule->template_rotation = [];
        $schedule->meta = [
            ...(is_array($schedule->meta) ? $schedule->meta : []),
            'weekly_plan' => $weeklyPlan,
        ];
        $schedule->save();

        return back()->with('success', [
            'message' => $regions->regionLabel($regionKey).' outreach plan cleared. Imported contacts were kept.',
        ]);
    }

    public function unsubscribe(Request $request, OutreachContact $contact): ViewContract
    {
        abort_unless($request->hasValidSignature(), 403);

        $alreadyUnsubscribed = $contact->status === OutreachContact::STATUS_UNSUBSCRIBED;

        if (! $alreadyUnsubscribed) {
            $contact->forceFill([
                'status' => OutreachContact::STATUS_UNSUBSCRIBED,
                'last_result' => 'unsubscribed',
            ])->save();
        }

        return view('outreach.unsubscribe', [
            'contact' => $contact,
            'alreadyUnsubscribed' => $alreadyUnsubscribed,
            'appName' => (string) config('app.name', 'Sea Requests'),
            'homeUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:180'],
            'body_text' => ['required', 'string', 'min:20'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
    }

    private function validateSenderAccount(Request $request, ?OutreachSenderAccount $sender = null): array
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'from_name' => ['required', 'string', 'max:120'],
            'from_email' => ['required', 'email', 'max:255'],
            'reply_to_email' => ['nullable', 'email', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => $sender
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ]);

        $validated['name'] = filled($validated['name'] ?? null)
            ? $validated['name']
            : $validated['from_email'];

        $validated['reply_to_email'] = filled($validated['reply_to_email'] ?? null)
            ? $validated['reply_to_email']
            : $validated['from_email'];

        if ($sender && blank($validated['smtp_password'] ?? null)) {
            unset($validated['smtp_password']);
        }

        if (($validated['is_default'] ?? false) === true) {
            $validated['is_active'] = true;
        }

        if (($validated['is_active'] ?? true) === false) {
            $validated['is_default'] = false;
        }

        return $validated;
    }

    private function sendSenderTestMail(Request $request, OutreachSenderAccount $sender, OutreachDynamicMailer $mailer): RedirectResponse
    {
        $recipientEmail = (string) ($sender->from_email ?? '');

        if (blank($recipientEmail)) {
            return back()->with('error', [
                'message' => 'A valid sender mailbox is required before a test email can be sent.',
            ]);
        }

        $appName = (string) config('app.name', 'Sea Requests');
        $subject = $appName.' sender account test';
        $body = implode("\n", [
            'This is a sender account test email from '.$appName.'.',
            '',
            'If you received this message, the SMTP settings below are working.',
            '',
            'From Name: '.$sender->from_name,
            'From Email: '.$sender->from_email,
            'SMTP Host: '.$sender->smtp_host,
            'SMTP Port: '.$sender->smtp_port,
            'Connection Security: '.($sender->smtp_encryption ? strtoupper((string) $sender->smtp_encryption) : 'None'),
            'Sent At: '.now('Europe/Istanbul')->format('d/m/Y H:i'),
        ]);

        try {
            $mailer->send(
                $sender,
                $recipientEmail,
                new OutreachSenderTestMail(
                    $subject,
                    $body,
                    $sender->from_email,
                    $sender->from_name,
                    $sender->reply_to_email
                )
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', [
                'message' => 'Test email could not be sent. Please check the SMTP host, port, username, password, and security settings.',
            ]);
        }

        return back()->with('success', [
            'message' => 'Test email sent to '.$recipientEmail.'.',
        ]);
    }

    private function transformContactsPage(
        LengthAwarePaginator $contactsPage,
        OutreachRegionManager $regions,
        string $statusFilter,
        string $regionFilter,
        string $searchFilter
    ): array {
        return [
            'data' => collect($contactsPage->items())
                ->map(function (OutreachContact $contact) use ($regions) {
                    $regionKey = (string) ($contact->primarySegment?->region_key ?: 'global');

                    return [
                        'id' => $contact->id,
                        'email' => $contact->email,
                        'organization_name' => $contact->organization_name,
                        'source_name' => $contact->source_name,
                        'status' => $contact->status,
                        'region_key' => $regionKey,
                        'region_label' => $regions->regionLabel($regionKey),
                        'segment_name' => $contact->primarySegment?->name,
                        'last_sent_at' => optional($contact->last_sent_at)->toIso8601String(),
                        'sent_count' => (int) $contact->sent_count,
                        'last_result' => $contact->last_result,
                        'source_type' => (string) data_get($contact->source_payload, 'source_type', 'import'),
                        'delete_url' => route('admin.outreach.contacts.destroy', $contact),
                    ];
                })
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $contactsPage->currentPage(),
                'last_page' => $contactsPage->lastPage(),
                'per_page' => $contactsPage->perPage(),
                'total' => $contactsPage->total(),
                'from' => $contactsPage->firstItem(),
                'to' => $contactsPage->lastItem(),
            ],
            'filters' => [
                'status' => $statusFilter,
                'region' => $regionFilter,
                'search' => $searchFilter,
            ],
        ];
    }
}
