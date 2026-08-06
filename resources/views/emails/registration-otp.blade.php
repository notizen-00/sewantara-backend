<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kode verifikasi {{ config('app.name') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="padding:32px;text-align:center;">
                <h1 style="font-size:18px;color:#111827;margin:0 0 16px;">{{ config('app.name') }}</h1>
                <p style="color:#374151;font-size:14px;line-height:1.5;margin:0 0 24px;">
                    Gunakan kode berikut untuk memverifikasi email Anda dan melanjutkan pendaftaran akun usaha.
                </p>
                <div style="font-size:32px;font-weight:700;letter-spacing:8px;color:#111827;background:#f3f4f6;padding:16px;border-radius:8px;margin:0 0 24px;">
                    {{ $code }}
                </div>
                <p style="color:#6b7280;font-size:12px;line-height:1.5;margin:0;">
                    Kode ini berlaku selama {{ $ttlMinutes }} menit. Jangan bagikan kode ini kepada siapa pun,
                    termasuk pihak yang mengaku dari {{ config('app.name') }}.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
