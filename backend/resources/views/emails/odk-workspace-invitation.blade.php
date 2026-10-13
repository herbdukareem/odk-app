<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DataKit workspace invitation</title>
</head>
<body style="margin:0;background:#f4f7f5;color:#123126;font-family:Arial,sans-serif;line-height:1.5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;background:#f4f7f5;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d8e4dd;border-radius:10px;">
                    <tr>
                        <td style="padding:28px 32px 18px;background:#064b35;color:#ffffff;border-radius:10px 10px 0 0;">
                            <div style="font-size:24px;font-weight:700;">Data<span style="color:#8fe3b5;">Kit</span></div>
                            <div style="font-size:12px;color:#d9f2e4;">Quality assurance workspace</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px;">
                            <h1 style="margin:0 0 16px;font-size:24px;color:#123126;">You are invited to join a workspace</h1>
                            <p style="margin:0 0 16px;">You have been invited to join <strong>{{ $workspace->name }}</strong> as a <strong>{{ ucfirst($roleKey) }}</strong>.</p>
                            <p style="margin:0 0 24px;">Use the button below to sign in and accept the invitation.</p>
                            <p style="margin:0 0 24px;">
                                <a href="{{ $inviteUrl }}" style="display:inline-block;padding:13px 22px;background:#087a4a;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:700;">Accept invitation</a>
                            </p>
                            <p style="margin:0 0 8px;font-size:13px;color:#60746a;">This invitation expires on {{ $expiresAt }}.</p>
                            <p style="margin:0;font-size:13px;color:#60746a;">If the button does not work, copy and paste this link into your browser:</p>
                            <p style="margin:8px 0 0;word-break:break-all;font-size:12px;color:#087a4a;">{{ $inviteUrl }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;border-top:1px solid #e5ece8;font-size:12px;color:#60746a;">
                            DataKit - Better data. Stronger healthcare.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
