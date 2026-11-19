<!DOCTYPE html>
<html lang="en">
<body style="margin:0; padding:24px; background:#f6f7f9; font-family:Segoe UI, Arial, sans-serif; color:#1c2430;">
    <div style="max-width:480px; margin:0 auto; background:#ffffff; border:1px solid #dfe3ea; border-radius:8px; padding:24px;">
        <h1 style="font-size:20px; margin:0 0 12px;">You have been invited to {{ $invitation->organization->name }}</h1>

        <p style="margin:0 0 16px; line-height:1.5;">
            You have been invited to join <strong>{{ $invitation->organization->name }}</strong>
            on Project Tracker as <strong>{{ $invitation->role->value }}</strong>.
        </p>

        <p style="margin:0 0 20px;">
            <a href="{{ $acceptUrl }}" style="display:inline-block; background:#1f6f5c; color:#ffffff; text-decoration:none; font-weight:600; padding:10px 16px; border-radius:6px;">Accept invitation</a>
        </p>

        <p style="margin:0 0 8px; line-height:1.5; color:#5d6878; font-size:14px;">
            Log in, or register with this email address ({{ $invitation->email }}), and you will be taken to the invitation.
            The link is valid until {{ $invitation->expires_at->format('j F Y') }}.
        </p>

        <p style="margin:0; line-height:1.5; color:#5d6878; font-size:14px; word-break:break-all;">
            If the button does not work, open this address: {{ $acceptUrl }}
        </p>
    </div>
</body>
</html>
