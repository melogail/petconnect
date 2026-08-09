<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f5f3ff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#3F4654;-webkit-text-size-adjust:100%;">
    <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3ff;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding-inline-end:10px;vertical-align:middle;">
                                        <div style="width:40px;height:40px;border-radius:16px;background-color:#7C3AED;text-align:center;line-height:40px;">
                                            <span style="display:inline-block;width:22px;height:22px;border-radius:999px;background-color:#ffffff;vertical-align:middle;"></span>
                                        </div>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <span style="font-size:20px;font-weight:700;color:#1f2937;letter-spacing:-0.02em;">{{ $appName }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff;border:1px solid #ede9fe;border-radius:16px;overflow:hidden;box-shadow:0 18px 40px rgba(124,58,237,0.12);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background:linear-gradient(135deg,#7C3AED 0%,#9333EA 45%,#D946EF 100%);background-color:#7C3AED;padding:36px 28px 40px;text-align:center;">
                                        <div style="width:64px;height:64px;margin:0 auto 18px;border-radius:16px;background-color:rgba(255,255,255,0.16);border:1px solid rgba(255,255,255,0.28);text-align:center;line-height:64px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" role="img" aria-hidden="true" style="display:inline-block;vertical-align:middle;color:#ffffff;">
                                                <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                            </svg>
                                        </div>
                                        <h1 style="margin:0 0 10px;font-size:24px;line-height:1.3;font-weight:700;color:#ffffff;">
                                            {{ $heading }}
                                        </h1>
                                        <p style="margin:0 auto;max-width:420px;font-size:14px;line-height:1.6;color:rgba(245,243,255,0.95);">
                                            {{ $intro }}
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:28px 28px 8px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f3ff;border:1px solid #ede9fe;border-radius:12px;">
                                            <tr>
                                                <td style="padding:14px 16px;">
                                                    <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#6D28D9;">
                                                        {{ $accountEmailLabel }}
                                                    </p>
                                                    <p style="margin:0;font-size:14px;font-weight:700;color:#111827;word-break:break-all;">
                                                        {{ $userEmail }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:20px 28px 8px;text-align:center;">
                                        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#4B5563;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
                                            {{ $body }}
                                        </p>

                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $url }}" style="height:48px;v-text-anchor:middle;width:240px;" arcsize="20%" stroke="f" fillcolor="#7C3AED">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:sans-serif;font-size:15px;font-weight:bold;">
                                                {{ $action }}
                                            </center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-- -->
                                        <a href="{{ $url }}"
                                           style="display:inline-block;background:linear-gradient(90deg,#7C3AED,#D946EF);background-color:#7C3AED;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 28px;border-radius:10px;box-shadow:0 10px 20px rgba(124,58,237,0.28);">
                                            {{ $action }}
                                        </a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:24px 28px 8px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #F3F4F6;">
                                            <tr>
                                                <td style="padding-top:20px;">
                                                    <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#1F2937;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
                                                        {{ $tipsTitle }}
                                                    </p>
                                                    <p style="margin:0 0 8px;font-size:13px;line-height:1.55;color:#6B7280;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
                                                        • {{ $tipExpiry }}
                                                    </p>
                                                    <p style="margin:0;font-size:13px;line-height:1.55;color:#6B7280;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
                                                        • {{ $tipIgnore }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:20px 28px 28px;">
                                        <p style="margin:0 0 8px;font-size:12px;line-height:1.5;color:#9CA3AF;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
                                            {{ $linkFallback }}
                                        </p>
                                        <p style="margin:0;font-size:12px;line-height:1.5;word-break:break-all;text-align:{{ $dir === 'rtl' ? 'right' : 'left' }};">
                                            <a href="{{ $url }}" style="color:#7C3AED;text-decoration:underline;">{{ $url }}</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:24px 8px 0;">
                            <p style="margin:0 0 6px;font-size:12px;color:#9CA3AF;">
                                {{ $footer }}
                            </p>
                            <p style="margin:0;font-size:12px;color:#C4B5FD;">
                                {{ config('app.url') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
