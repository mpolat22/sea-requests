<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Contact Form Message</title>
    </head>
    <body style="font-family: Arial, sans-serif; color: #04151f; line-height: 1.6;">
        <h2 style="margin-bottom: 16px;">New Contact Message</h2>

        <p><strong>Name:</strong> {{ $payload['name'] }}</p>
        <p><strong>Email:</strong> {{ $payload['email'] }}</p>
        <p><strong>Phone:</strong> {{ $payload['phone'] }}</p>
        <p><strong>Subject:</strong> {{ $payload['subject'] ?: '-' }}</p>

        <p style="margin-top: 24px;"><strong>Message:</strong></p>
        <p>{!! nl2br(e($payload['message'])) !!}</p>
    </body>
</html>
