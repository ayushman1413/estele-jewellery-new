@component('emails.layout', [
    'title' => 'Jewellery Valuation Complete',
    'heading' => 'Your jewellery valuation is complete',
    'preheader' => '₹'.number_format((float) $credit->credited_amount, 2).' has been credited to your Estele wallet for request '.$credit->request->request_number.'.',
])

<p style="margin:0 0 16px;">Hi {{ $credit->request->user->name }},</p>

<p style="margin:0 0 16px;">
    Great news! Your old jewellery request <strong style="color:#1F1D1D;">{{ $credit->request->request_number }}</strong> has been valued and the credit has been added to your wallet.
</p>

{{-- Wallet card --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FBF1F4; border:1px solid #EAE4DE; border-radius:6px; margin:0 0 16px;">
    <tr>
        <td style="padding:20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:12px; color:#8C807B; text-transform:uppercase; letter-spacing:1px; padding-bottom:12px;">Valuation Summary</td>
                </tr>
                <tr>
                    <td style="padding-bottom:8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F;">Amount Credited</td>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#007F46; font-weight:600; text-align:right;">
                                    + ₹{{ number_format((float) $credit->credited_amount, 2) }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #EAE4DE; padding-top:8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F;">Request</td>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#1F1D1D; font-weight:600; text-align:right;">
                                    {{ $credit->request->request_number }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #EAE4DE; padding-top:8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F;">Credit Expires</td>
                                <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#D4AF37; font-weight:700; text-align:right;">
                                    {{ $credit->expires_at->format('d M Y') }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0 0 16px; color:#666666; font-size:13px;">
    Use your wallet balance on your next purchase at Estele before it expires.
</p>

@slot('cta')
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
    <tr>
        <td style="background: linear-gradient(135deg, #CB6B88, #B12B4D); border-radius:6px;">
            <a href="{{ url('/') }}" style="display:inline-block; padding:12px 32px; font-family:Helvetica,Arial,sans-serif; font-size:13px; font-weight:700; color:#FFFFFF; text-decoration:none; text-transform:uppercase; letter-spacing:0.5px;">
                Shop Now
            </a>
        </td>
    </tr>
</table>
@endslot

@endcomponent
