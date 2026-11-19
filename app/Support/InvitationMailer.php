<?php

namespace App\Support;

use App\Models\Invitation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends invitation emails through Brevo's HTTPS API.
 *
 * HTTPS rather than SMTP because the host's free tier blocks outbound SMTP
 * ports. The whole invitation feature is switched on by configuration: with
 * no BREVO_API_KEY it is off and the app behaves exactly as it did before.
 */
class InvitationMailer
{
    public static function enabled(): bool
    {
        return filled(config('services.brevo.key')) && filled(config('services.brevo.from_address'));
    }

    /** Returns true when Brevo accepted the email. Never throws. */
    public function send(Invitation $invitation, string $acceptUrl): bool
    {
        try {
            $response = Http::withHeaders(['api-key' => config('services.brevo.key')])
                ->acceptJson()
                ->timeout(10)
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'sender' => [
                        'name' => config('services.brevo.from_name'),
                        'email' => config('services.brevo.from_address'),
                    ],
                    'to' => [['email' => $invitation->email]],
                    'subject' => "You have been invited to {$invitation->organization->name}",
                    'htmlContent' => view('emails.invitation', [
                        'invitation' => $invitation,
                        'acceptUrl' => $acceptUrl,
                    ])->render(),
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Brevo rejected an invitation email.', ['status' => $response->status()]);
        } catch (Throwable $exception) {
            Log::warning('Could not reach Brevo to send an invitation email.', ['error' => $exception->getMessage()]);
        }

        return false;
    }
}
