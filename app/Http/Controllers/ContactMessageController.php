<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use App\Support\UserFacingMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactMessageController extends Controller
{
    public function store(Request $request, UserFacingMail $mail): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:7', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'agree_to_contact' => ['accepted'],
        ], [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Phone is required.',
            'message.required' => 'Message is required.',
            'message.min' => 'Please tell us a bit more about your enquiry.',
            'agree_to_contact.accepted' => 'You must accept the contact consent text to continue.',
        ]);

        $recipient = (string) config('mail.support_mail.recipient', 'support@searequests.ai');

        $delivery = $mail->attempt(function () use ($recipient, $validated): void {
            Mail::mailer('support')
                ->to($recipient)
                ->send(new ContactMessageMail($validated));
        });

        if (! $delivery['ok']) {
            return back()->with('error', 'We could not send your message right now. Please try again shortly.');
        }

        return back()->with('success', 'Your message has been sent successfully.');
    }
}
