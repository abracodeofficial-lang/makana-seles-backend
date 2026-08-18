<?php
// حذف ملفات الكاش مباشرة بدون Artisan
$base = __DIR__ . '/..';
$files = glob($base . '/bootstrap/cache/*.php');
$deleted = [];

foreach ($files as $file) {
    if (basename($file) !== 'packages.php') {
        unlink($file);
        $deleted[] = basename($file);
    }
}

echo 'Deleted: ' . (count($deleted) ? implode(', ', $deleted) : 'nothing to clear');
