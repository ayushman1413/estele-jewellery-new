@component('emails.layout', [
    'title' => 'Order '.$order->order_number.' packed',
    'heading' => 'Your order is packed! ✨',
    'preheader' => 'Order '.$order->order_number.' is ready for shipping.',
])

<p style="margin:0 0 16px;">Hi {{ $order->customer_name }},</p>

<p style="margin:0 0 16px;">
    Great news — your order has been packed with care and is ready for shipping.
</p>

{{-- Order details card --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FBF1F4; border:1px solid #EAE4DE; border-radius:6px; margin:0 0 16px;">
    <tr>
        <td style="padding:16px 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:12px; color:#8C807B; text-transform:uppercase; letter-spacing:1px; padding-bottom:10px;">Order Details</td>
                </tr>
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F; padding-bottom:6px;">
                        <strong style="color:#1F1D1D;">Order #:</strong> {{ $order->order_number }}
                    </td>
                </tr>
                <tr>
                    <td style="font-family:Helvetica,Arial,sans-serif; font-size:14px; color:#33302F;">
                        <strong style="color:#1F1D1D;">Total:</strong>
                        <span style="color:#AD3D5F; font-weight:600;">&#8377;{{ number_format((float) $order->total, 2) }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<p style="margin:0 0 4px; color:#666666; font-size:13px;">
    We'll send you another update once your order ships. Sit tight!
</p>

@endcomponent
