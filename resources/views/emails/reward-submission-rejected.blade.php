@component('emails.layout', [
    'title' => 'Reward submission update',
    'heading' => 'Reward Submission Update',
    'preheader' => 'Your reward submission for order '.$submission->order->order_number.' was not approved.',
])

<p style="margin:0 0 16px;">Hi {{ $submission->user->name }},</p>

<p style="margin:0 0 16px;">
    We've reviewed your reward submission for order
    <strong style="color:#1F1D1D;">{{ $submission->order->order_number }}</strong>
    and unfortunately it was not approved this time.
</p>

{{-- Reason card --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FBF1F4; border:1px solid #EAE4DE; border-radius:6px; margin:0 0 16px;">
    <tr>
        <td style="padding:16px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:12px; color:#8C807B; text-transform:uppercase; letter-spacing:1px; padding-bottom:8px;">Reason</td>
                </tr>
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F; line-height:20px;">
                        {{ $submission->rejection_reason }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0; color:#666666; font-size:13px; line-height:20px;">
    You're welcome to submit proof for a different eligible order at any time.
    We appreciate your support!
</p>

@endcomponent
