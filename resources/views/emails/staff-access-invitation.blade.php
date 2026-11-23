@component('emails.layout', [
    'title' => $isResend ? 'Your password link' : 'Set up your account',
    'heading' => $isResend ? 'Your password link' : 'Welcome, '.$name,
    'preheader' => $isResend ? 'Here is a fresh link to set your password.' : 'You have been given '.$roleLabel.' access to '.$brand.'.',
])

<p style="margin:0 0 14px;">
    @if($isResend)
        Here is a fresh link to set your password for your
        {{ $brand }} account.
    @else
        You have been given {{ $roleLabel }} access to the
        {{ $brand }} admin panel. Choose your password using the
        button below, then sign in with your email address.
    @endif
</p>

@slot('cta')
<table role="presentation" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background-color:#CB6B88; border-radius:4px;">
            <a href="{{ $setupUrl }}"
               style="display:inline-block; padding:14px 34px; font-family:Helvetica,Arial,sans-serif; font-size:12px; font-weight:600; letter-spacing:1px; text-transform:uppercase; color:#FFFFFF; text-decoration:none;">
                Set your password
            </a>
        </td>
    </tr>
</table>
@endslot

<p style="margin:0 0 14px; font-size:13px; line-height:20px; color:#666666;">
    This link is valid for {{ $expiryHours }} hours. If it expires,
    ask your {{ $brand }} contact to send a new one.
</p>

<p style="margin:0 0 6px; font-size:12px; line-height:18px; color:#666666;">
    If the button does not work, copy this address into your browser:
</p>
<p style="margin:0 0 16px; font-size:12px; line-height:18px; word-break:break-all;">
    <a href="{{ $setupUrl }}" style="color:#AD3D5F;">{{ $setupUrl }}</a>
</p>

<p style="margin:0; font-size:12px; line-height:18px; color:#666666;">
    Didn't expect this email? You can ignore it — no account is
    active until a password is set.
</p>

@endcomponent
