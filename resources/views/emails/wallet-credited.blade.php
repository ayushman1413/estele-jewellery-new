@component('emails.layout', [
    'title' => 'Wallet Updated',
    'heading' => 'Your wallet has been updated',
    'preheader' => '₹'.number_format((float) $transaction->amount, 2).' has been credited to your Estele wallet.',
])

<p style="margin:0 0 16px;">Hi {{ $transaction->user->name }},</p>

<p style="margin:0 0 16px;">
    @if($transaction->reason === 'reward_approved')
        Congratulations! Your reward has been approved.
    @elseif($transaction->reason === 'order_refund')
        Your refund has been processed successfully.
    @else
        A credit has been added to your wallet.
    @endif
</p>

{{-- Wallet card --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FBF1F4; border:1px solid #EAE4DE; border-radius:6px; margin:0 0 16px;">
    <tr>
        <td style="padding:20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:12px; color:#8C807B; text-transform:uppercase; letter-spacing:1px; padding-bottom:12px;">Wallet Summary</td>
                </tr>
                <tr>
                    <td style="padding-bottom:8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F;">Amount Credited</td>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#007F46; font-weight:600; text-align:right;">
                                    + ₹{{ number_format((float) $transaction->amount, 2) }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #EAE4DE; padding-top:8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#1F1D1D; font-weight:600;">Current Balance</td>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:18px; color:#D4AF37; font-weight:700; text-align:right;">
                                    ₹{{ number_format((float) $transaction->balance_after, 2) }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0; color:#666666; font-size:13px;">
    You can use your wallet balance on your next purchase at Estele.
</p>

@endcomponent
