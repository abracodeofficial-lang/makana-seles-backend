<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <title>تم إضافتك كموظف</title>
</head>
<body style="margin:0; padding:0; background:#0f172a; font-family: Tahoma, Arial, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#1e293b; border-radius:16px; overflow:hidden;">
                    <tr>
                        <td style="background:#2563eb; padding:24px; text-align:center;">
                            <span style="font-size:22px;">🏢</span>
                            <h1 style="color:#ffffff; font-size:18px; margin:8px 0 0;">نظام الإدارة</h1>
                            <p style="color:#dbeafe; font-size:12px; margin:4px 0 0;">المبيعات والموارد البشرية</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 24px; color:#e2e8f0; text-align:right;" dir="rtl">
                            <p style="font-size:16px; margin:0 0 16px;">مرحباً {{ $fullName }}،</p>
                            <p style="font-size:14px; line-height:1.8; margin:0 0 16px; color:#cbd5e1;">
                                تم إضافتك كموظف ضمن نظام إدارة المبيعات والموارد البشرية.
                                فيما يلي بريدك الإلكتروني المستخدم لتسجيل الدخول:
                            </p>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a; border-radius:10px; margin:0 0 20px;">
                                <tr>
                                    <td style="padding:14px 16px; font-size:14px; color:#93c5fd; direction:ltr; text-align:center;">
                                        {{ $email }}
                                    </td>
                                </tr>
                            </table>
                            <p style="font-size:13px; line-height:1.8; margin:0 0 20px; color:#94a3b8;">
                                لتسجيل الدخول لأول مرة، تواصل مع المسؤول أو قسم الموارد البشرية للحصول على كلمة المرور الخاصة بك.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto;">
                                <tr>
                                    <td style="background:#2563eb; border-radius:10px;">
                                        <a href="{{ $loginUrl }}" style="display:inline-block; padding:12px 28px; color:#ffffff; font-size:14px; font-weight:bold; text-decoration:none;">
                                            الدخول إلى النظام
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
