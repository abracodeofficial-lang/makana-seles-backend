<?php
// بيكتب إعدادات البريد بملف .env عالسيرفر من أسرار GitHub (بيتشغّل من deploy.yml).
// لو ما في MAIL_USERNAME/MAIL_PASSWORD بالأسرار ما بيلمس شي.

$user = getenv('MAIL_USERNAME') ?: '';
$pass = getenv('MAIL_PASSWORD') ?: '';
if ($user === '' || $pass === '') {
    echo "mail env: secrets not set, skipped\n";
    exit(0);
}

$values = [
    'MAIL_MAILER'       => 'smtp',
    'MAIL_HOST'         => getenv('MAIL_HOST') ?: 'smtp.hostinger.com',
    'MAIL_PORT'         => getenv('MAIL_PORT') ?: '465',
    'MAIL_ENCRYPTION'   => getenv('MAIL_ENCRYPTION') ?: 'ssl',
    'MAIL_USERNAME'     => $user,
    'MAIL_PASSWORD'     => $pass,
    'MAIL_FROM_ADDRESS' => getenv('MAIL_FROM_ADDRESS') ?: $user,
    'MAIL_FROM_NAME'    => 'نظام الإدارة',
];

// قيمة آمنة لـ dotenv: بين '' لو ما فيها '، وإلا بين "" مع تهريب \ و " و $
$quote = function (string $v): string {
    if (!str_contains($v, "'")) return "'" . $v . "'";
    return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $v) . '"';
};

$path = __DIR__ . '/../.env';
$env  = is_file($path) ? file_get_contents($path) : '';

foreach ($values as $key => $value) {
    $line = $key . '=' . $quote($value);
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    $env = preg_match($pattern, $env)
        ? preg_replace_callback($pattern, fn() => $line, $env, 1)
        : rtrim($env, "\n") . "\n" . $line . "\n";
}

file_put_contents($path, $env);
echo "mail env: updated\n";
