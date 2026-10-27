<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $copy['subject'] }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;color:#0b0d12;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $copy['preheader'] }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7;">
        <tr><td align="center" style="padding:32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:24px;overflow:hidden;">
                <tr><td style="padding:28px 32px;background-color:#0b0d12;">
                    <a href="{{ $frontend }}" style="text-decoration:none;color:#ffffff;">
                        <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                            <td style="padding-right:12px;"><img src="{{ $message->embed(resource_path('mail/logo.png')) }}" width="40" height="40" alt="" style="display:block;border:0;"></td>
                            <td style="font-size:23px;font-weight:bold;color:#ffffff;letter-spacing:-0.6px;">veiligerbuiten</td>
                        </tr></table>
                    </a>
                </td></tr>
                <tr><td style="padding:32px;">
                    <p style="margin:0 0 12px;color:#2f6bff;font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;">{{ $copy['eyebrow'] }}</p>
                    <h1 style="margin:0 0 24px;font-size:34px;line-height:1.15;letter-spacing:-1px;color:#0b0d12;">{{ $copy['title'] }}</h1>
                    <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">{{ $common['hello'] }} {{ $name }},</p>
                    <p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#414754;">{{ $copy['intro'] }}</p>
                    <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#626977;">{{ $common['email'] }}: <strong style="color:#0b0d12;">{{ $email }}</strong></p>
                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td bgcolor="#2f6bff" style="border-radius:14px;text-align:center;">
                        <a href="{{ $url }}" style="display:inline-block;padding:16px 24px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:bold;line-height:1.4;">{{ $copy['action'] }}</a>
                    </td></tr></table>
                    <p style="margin:12px 0 28px;color:#626977;font-size:13px;line-height:1.5;">{{ str_replace(':minutes', (string) $minutes, $common['expires']) }}</p>
                    @if (!empty($copy['details']))
                        <h2 style="margin:0 0 12px;font-size:19px;line-height:1.4;color:#0b0d12;">{{ $copy['details_title'] }}</h2>
                        <ul style="padding-left:20px;margin:0 0 24px;color:#414754;font-size:15px;line-height:1.7;">
                            @foreach ($copy['details'] as $detail)
                                <li style="padding-bottom:10px;">{{ $detail }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (!empty($copy['note']))
                        <p style="margin:0 0 20px;padding:16px;background-color:#f4f5f7;border-radius:12px;font-size:14px;line-height:1.6;color:#414754;">{{ $copy['note'] }}</p>
                    @endif
                    <p style="margin:0 0 24px;color:#626977;font-size:14px;line-height:1.6;">{{ $copy['ignore'] }}</p>
                    <p style="margin:0 0 8px;color:#626977;font-size:12px;line-height:1.6;">{{ $common['fallback'] }}</p>
                    <p style="margin:0;font-size:12px;line-height:1.6;word-break:break-all;overflow-wrap:anywhere;"><a href="{{ $url }}" style="color:#2f6bff;">{{ $url }}</a></p>
                </td></tr>
                <tr><td style="padding:24px 32px;border-top:1px solid #e5e7eb;font-size:13px;line-height:1.8;color:#626977;">
                    <a href="{{ $frontend }}/account" style="color:#2f6bff;">{{ $common['account'] }}</a><br>
                    {{ $common['help'] }} <a href="mailto:hallo@veiligerbuiten.nl" style="color:#626977;">hallo@veiligerbuiten.nl</a><br>
                    <a href="{{ $frontend }}/privacy" style="color:#626977;">{{ $common['privacy'] }}</a> · <a href="{{ $frontend }}/voorwaarden" style="color:#626977;">{{ $common['terms'] }}</a><br>
                    VeiligerBuiten · Be safe.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
