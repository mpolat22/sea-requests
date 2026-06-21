<?php

namespace App\Support\Outreach;

use App\Models\OutreachContact;
use App\Models\OutreachImportRun;
use App\Models\OutreachSegment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

class OutreachCsvImporter
{
    public function __construct(
        protected OutreachLabelParser $labels,
        protected OutreachRegionManager $regions
    ) {}

    public function import(OutreachImportRun $run): void
    {
        $registeredSellerEmails = User::query()
            ->where('role', 'seller')
            ->pluck('email')
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->flip();

        $path = Storage::path($run->stored_path);
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',');

        $headers = null;
        $sourceSegmentCache = [];
        $regionSegmentCache = [];
        $touchedRegionSegmentIds = [];
        $seenEmails = [];
        $rowCount = 0;
        $processedCount = 0;
        $newContacts = 0;
        $updatedContacts = 0;
        $duplicateEmails = 0;
        $skipped = 0;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = $row;
                continue;
            }

            $rowCount++;
            $record = $this->mapRow($headers, $row);

            $label = $this->labels->parsePrimaryLabel($record['Labels'] ?? $record['Categories'] ?? null);

            if (! $this->labels->isSupplierLabel($label)) {
                $skipped++;
                continue;
            }

            $emails = collect([
                $record['E-mail 1 - Value'] ?? $record['E-mail Address'] ?? null,
                $record['E-mail 2 - Value'] ?? $record['E-mail 2 Address'] ?? null,
                $record['E-mail 3 Address'] ?? null,
            ])->map(fn ($value) => mb_strtolower(trim((string) $value)))
                ->filter()
                ->unique()
                ->values();

            if ($emails->isEmpty()) {
                $skipped++;
                continue;
            }

            $sourceSegment = $sourceSegmentCache[$label] ??= $this->regions->ensureSourceSegment($label);
            $regionKey = (string) ($sourceSegment->region_key ?: $this->labels->detectRegion($label) ?: 'global');
            $regionSegment = $regionSegmentCache[$regionKey] ??= $this->regions->ensureCanonicalSegment($regionKey);
            $touchedRegionSegmentIds[$regionSegment->id] = $regionSegment->id;

            foreach ($emails as $email) {
                if (isset($seenEmails[$email])) {
                    $duplicateEmails++;
                }

                $seenEmails[$email] = true;
                $processedCount++;

                DB::transaction(function () use (
                    $email,
                    $record,
                    $segment,
                    $registeredSellerEmails,
                    &$newContacts,
                    &$updatedContacts
                ) {
                    $contact = OutreachContact::query()->firstOrNew(['email' => $email]);
                    $isNew = ! $contact->exists;
                    $sourcePayload = is_array($contact->source_payload) ? $contact->source_payload : [];

                    $contact->audience = 'supplier';
                    $contact->organization_name = ($record['Organization Name'] ?? $record['Company'] ?? null) ?: $contact->organization_name;
                    $contact->source_name = trim(($record['First Name'] ?? '').' '.($record['Last Name'] ?? '')) ?: $contact->source_name;
                    $contact->primary_segment_id = $regionSegment->id;
                    $contact->source_payload = [
                        ...$sourcePayload,
                        'label' => $regionSegment->name,
                        'raw_label' => $sourceSegment->name,
                        'region_key' => $regionKey,
                        'organization_name' => $record['Organization Name'] ?? $record['Company'] ?? null,
                        'file_as' => $record['File As'] ?? null,
                    ];

                    if ($registeredSellerEmails->has($email)) {
                        $contact->status = OutreachContact::STATUS_REGISTERED;
                    } elseif (! in_array($contact->status, [
                        OutreachContact::STATUS_UNSUBSCRIBED,
                        OutreachContact::STATUS_REPLIED,
                    ], true)) {
                        $contact->status = OutreachContact::STATUS_ACTIVE;
                    }

                    $contact->save();
                    $contact->segments()->syncWithoutDetaching([$regionSegment->id, $sourceSegment->id]);

                    if ($isNew) {
                        $newContacts++;
                    } else {
                        $updatedContacts++;
                    }
                });
            }
        }

        OutreachSegment::query()
            ->withCount(['primaryContacts as primary_contacts_count' => fn ($query) => $query->where('audience', 'supplier')])
            ->whereIn('id', array_values($touchedRegionSegmentIds))
            ->get()
            ->each(function (OutreachSegment $segment) {
                $this->regions->syncCanonicalSchedule($segment, (int) ($segment->primary_contacts_count ?? 0));
            });

        $this->regions->normalizeSupplierWorkspace();

        $run->forceFill([
            'status' => 'completed',
            'row_count' => $rowCount,
            'processed_count' => $processedCount,
            'new_contacts_count' => $newContacts,
            'updated_contacts_count' => $updatedContacts,
            'duplicate_emails_count' => $duplicateEmails,
            'skipped_count' => $skipped,
            'summary' => [
                'regions_touched' => count($touchedRegionSegmentIds),
                'unique_emails_seen' => count($seenEmails),
            ],
            'completed_at' => now(),
            'message' => count($touchedRegionSegmentIds) > 0
                ? 'Supplier contacts imported successfully.'
                : 'No supplier-labelled rows matched this file. Upload the supplier contact export with labels such as SUPPLIER EUROPE-1.',
        ])->save();
    }

    private function mapRow(array $headers, array $row): array
    {
        $values = array_pad($row, count($headers), null);

        return collect($headers)
            ->mapWithKeys(fn ($header, $index) => [trim((string) $header) => trim((string) ($values[$index] ?? ''))])
            ->all();
    }

}
