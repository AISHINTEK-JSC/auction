{{--
    The one layout of every email the site sends. Its look (logo, colour,
    greeting, signature, footer) comes from App\Support\EmailTemplate and is
    edited in the admin panel (Mẫu email).

    Tables and inline styles on purpose: that is what email programs render
    reliably. Texts are printed escaped, never as Markdown or HTML, since they
    contain what members typed (listing titles, moderators' notes).
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $subject ?? $siteName }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px;">
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <a href="{{ $siteUrl }}" style="text-decoration: none; color: #18181b;">
                                @if ($logoUrl)
                                    <img src="{{ $logoUrl }}" width="40" height="40" alt="" style="display: inline-block; vertical-align: middle; border: 0; border-radius: 10px;">
                                @endif
                                <span style="display: inline-block; vertical-align: middle; padding-left: {{ $logoUrl ? '10px' : '0' }}; font-size: 20px; font-weight: 700;">{{ $siteName }}</span>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #ffffff; border: 1px solid #e4e4e7; border-top: 4px solid {{ $brandColor }}; border-radius: 12px; padding: 32px;">
                            @if (! empty($greeting))
                                <p style="margin: 0 0 16px; font-size: 18px; font-weight: 600; line-height: 1.4;">{{ $greeting }}</p>
                            @endif

                            @foreach ($introLines as $line)
                                <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">{{ $line }}</p>
                            @endforeach

                            @isset($actionText)
                                <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
                                    <tr>
                                        <td style="border-radius: 8px; background-color: {{ $brandColor }};">
                                            <a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="display: inline-block; padding: 12px 24px; font-size: 16px; font-weight: 600; color: {{ $buttonTextColor }}; text-decoration: none; border-radius: 8px;">{{ $actionText }}</a>
                                        </td>
                                    </tr>
                                </table>
                            @endisset

                            @foreach ($outroLines as $line)
                                <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.6;">{{ $line }}</p>
                            @endforeach

                            <p style="margin: 24px 0 0; font-size: 16px; line-height: 1.6;">
                                @foreach ($signatureLines as $line)
                                    {{ $line }}@if (! $loop->last)<br>@endif
                                @endforeach
                            </p>

                            @isset($actionText)
                                <p style="margin: 24px 0 0; padding-top: 16px; border-top: 1px solid #e4e4e7; font-size: 13px; line-height: 1.5; color: #71717a;">
                                    Nếu nút "{{ $actionText }}" không bấm được, hãy chép địa chỉ sau vào trình duyệt:
                                    <a href="{{ $actionUrl }}" style="color: {{ $brandColor }}; word-break: break-all;">{{ $displayableActionUrl }}</a>
                                </p>
                            @endisset
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding: 20px 16px 0; font-size: 13px; line-height: 1.6; color: #71717a;">
                            @foreach ($footerLines as $line)
                                {{ $line }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
