{{--
  Shared email layout — Estele brand.
  Mail clients strip <style> blocks and ignore flex/grid, so everything
  is table-layout with inline styles.

  Slots:
    $title    — <title> and preheader (string)
    $heading  — H1 text (string)
    $slot     — body content (HTML)
    $cta      — optional CTA button row (raw HTML, use the button partial)
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? '' }}</title>
    <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#FAF8F5; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

{{-- Preheader (hidden preview text in inbox list) --}}
@if(isset($preheader) && $preheader !== '')
<div style="display:none; max-height:0; overflow:hidden; font-size:1px; line-height:1px; color:#FAF8F5;">
    {{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
</div>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#FAF8F5; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#FFFFFF; border:1px solid #EAE4DE; border-radius:8px; overflow:hidden;">

                {{-- ====== HEADER: deep wine banner with gold wordmark ====== --}}
                <tr>
                    <td style="background-color:#2B141C; padding:24px 32px; text-align:center;">
                        <span style="font-family:Georgia,'Times New Roman',serif; font-size:26px; letter-spacing:3px; color:#D4AF37; text-transform:uppercase;">
                            ESTELE
                        </span>
                        <br>
                        <span style="font-family:Helvetica,Arial,sans-serif; font-size:10px; letter-spacing:2.5px; color:#D4AF37; text-transform:uppercase; opacity:0.7;">
                            Trusted since 1989
                        </span>
                    </td>
                </tr>

                {{-- ====== ACCENT LINE ====== --}}
                <tr>
                    <td style="height:3px; background: linear-gradient(90deg, #CB6B88, #D4AF37, #CB6B88); font-size:0; line-height:0;">&nbsp;</td>
                </tr>

                {{-- ====== HEADING ====== --}}
                @if(isset($heading) && $heading !== '')
                <tr>
                    <td style="padding:28px 32px 0; font-family:Georgia,'Times New Roman',serif;">
                        <h1 style="margin:0; font-size:20px; font-weight:600; color:#1F1D1D; line-height:1.4;">
                            {{ $heading }}
                        </h1>
                    </td>
                </tr>
                @endif

                {{-- ====== BODY CONTENT ====== --}}
                <tr>
                    <td style="padding:16px 32px 8px; font-family:Helvetica,Arial,sans-serif; font-size:14px; line-height:22px; color:#33302F;">
                        {{ $slot }}
                    </td>
                </tr>

                {{-- ====== CTA BUTTON ====== --}}
                @if(isset($cta) && (string) $cta !== '')
                <tr>
                    <td style="padding:8px 32px 8px;" align="center">
                        {{ $cta }}
                    </td>
                </tr>
                @endif

                {{-- ====== FOOTER DIVIDER ====== --}}
                <tr>
                    <td style="padding:16px 32px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr><td style="border-top:1px solid #EAE4DE; font-size:0; line-height:0; height:1px;">&nbsp;</td></tr>
                        </table>
                    </td>
                </tr>

                {{-- ====== IN-CARD FOOTER ====== --}}
                <tr>
                    <td style="padding:16px 32px 24px; font-family:Helvetica,Arial,sans-serif; font-size:12px; line-height:18px; color:#8C807B; text-align:center;">
                        <p style="margin:0 0 8px;">Thank you for choosing <strong style="color:#1F1D1D;">Estele</strong></p>
                        <p style="margin:0 0 12px;">
                            <a href="https://www.facebook.com/estelejewelery/" style="color:#CB6B88; text-decoration:none; margin:0 6px;">Facebook</a>
                            &middot;
                            <a href="https://www.instagram.com/estele.co/" style="color:#CB6B88; text-decoration:none; margin:0 6px;">Instagram</a>
                            &middot;
                            <a href="https://www.youtube.com/@estelejewellery" style="color:#CB6B88; text-decoration:none; margin:0 6px;">YouTube</a>
                        </p>
                        <p style="margin:0; font-size:11px; color:#A89F99;">
                            Estele Accessories Pvt. Ltd. &bull; Hyderabad, India<br>
                            <a href="mailto:info@estele.co" style="color:#A89F99;">info@estele.co</a> &bull; +91 82474 76318
                        </p>
                    </td>
                </tr>

            </table>

            {{-- ====== COPYRIGHT ====== --}}
            <p style="margin:16px 0 0; font-family:Helvetica,Arial,sans-serif; font-size:11px; color:#8C807B; text-align:center;">
                &copy; {{ date('Y') }} Estele Accessories Pvt. Ltd. All rights reserved.
            </p>
        </td>
    </tr>
</table>

</body>
</html>
