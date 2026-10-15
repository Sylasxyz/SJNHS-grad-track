<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BrevoMailer;
use App\Services\MailDiagnostics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Test outbound email, ported from admin/mail_test.php: connection checks plus
 * a form that sends one test message (Admin only).
 */
class MailTestController extends Controller
{
    public function show(Request $request, MailDiagnostics $diagnostics): View
    {
        return view('admin.mail-test', [
            'diagnostics' => $diagnostics->lines(),
            'outboundReady' => $diagnostics->outboundReady(),
            'defaultTo' => (string) $request->user()->Email,
        ]);
    }

    public function send(Request $request, BrevoMailer $mailer, MailDiagnostics $diagnostics): RedirectResponse
    {
        $data = $request->validate([
            'to_email' => ['required', 'string', 'email:filter'],
        ], [
            'to_email.*' => 'Enter a valid recipient email.',
        ]);

        $result = $mailer->send(
            $data['to_email'],
            'SMTP test — San Josef NHS Graduate Tracking',
            "This is a test message from the Graduate Tracking System.\n\n"
            .'If you received this, outbound email is working on '.now()->format('Y-m-d H:i:s T').".\n"
        );

        if ($result['ok']) {
            return back()->withInput()->with('success', 'Test email sent via brevo-api. Check the inbox (and spam).');
        }

        $detail = $diagnostics->outboundReady()
            ? $result['error']
            : 'Mail is not configured. '.$diagnostics->operatorSetupHint();

        return back()->withInput()
            ->with('error', 'Test email failed.')
            ->with('error_detail', $detail);
    }
}
