@component('emails.layout', [
    'title' => 'New reward submission',
    'heading' => 'New Reward Submission',
    'preheader' => 'A new reward submission from '.$submission->user->name.' is waiting for review.',
])

<p style="margin:0 0 16px;">A new reward submission is waiting for your review.</p>

{{-- Submission details card --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FBF1F4; border:1px solid #EAE4DE; border-radius:6px; margin:0 0 16px;">
    <tr>
        <td style="padding:16px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:12px; color:#8C807B; text-transform:uppercase; letter-spacing:1px; padding-bottom:12px;">Submission Details</td>
                </tr>
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F; padding-bottom:6px;">
                        <strong style="color:#1F1D1D;">Customer:</strong> {{ $submission->user->name }}
                    </td>
                </tr>
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F; padding-bottom:6px;">
                        <strong style="color:#1F1D1D;">Order:</strong> {{ $submission->order->order_number }}
                    </td>
                </tr>
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F;">
                        <strong style="color:#1F1D1D;">Submitted:</strong> {{ $submission->created_at->format('d M Y, h:i A') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0; color:#666666; font-size:13px;">
    Review it in the admin panel under Reward Submissions.
</p>

@endcomponent
