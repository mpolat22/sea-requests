<?php

namespace App\Support\Outreach;

use App\Models\OutreachContact;
use App\Models\OutreachSegment;
use App\Models\OutreachTemplate;
use Illuminate\Support\Facades\URL;

class OutreachTemplateRenderer
{
    public function render(OutreachTemplate $template, OutreachContact $contact, OutreachSegment $segment): array
    {
        $unsubscribeUrl = URL::signedRoute('outreach.unsubscribe', ['contact' => $contact->id]);

        $replacements = [
            '{{site_url}}' => rtrim((string) config('app.url'), '/'),
            '{{unsubscribe_url}}' => $unsubscribeUrl,
            '{{company_name}}' => $contact->organization_name ?: 'your company',
            '{{contact_email}}' => $contact->email,
            '{{segment_name}}' => $segment->name,
            '{{app_name}}' => (string) config('app.name', 'Sea Requests'),
            '{{sender_name}}' => (string) config('mail.from.name', config('app.name', 'Sea Requests')),
        ];

        return [
            'subject' => strtr($template->subject, $replacements),
            'body' => strtr($template->body_text, $replacements),
            'unsubscribe_url' => $unsubscribeUrl,
        ];
    }
}
