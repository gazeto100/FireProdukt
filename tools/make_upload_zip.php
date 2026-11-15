<?php
// Създава архив за качване на хостинга, БЕЗ локалните и служебни файлове.
//   php tools/make_upload_zip.php            -> dist/fireprodukt-upload-ГГГГММДД-ЧЧММ.zip
// Списъкът с изключения е описан и в README.md (раздел "Какво НЕ се качва").
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
$root = dirname(__DIR__);

// Пътища спрямо корена на проекта (папки завършват с /)
$exclude = [
    'config/config.php',          // създава се от инсталатора на сървъра
    'config/installed.lock',      // създава се от инсталатора на сървъра
    'config/dev-admin.local.txt', // локални тестови данни за вход
    'tools/',                     // служебни скриптове (шрифтове, проверки, този файл)
    'dist/',                      // готовите архиви
    'README.md',                  // документация – не е нужна на сървъра
    '.git/', '.idea/', '.vscode/', '.claude/',
];
$excludeGlobs = ['*.local.*', '*.log', '.DS_Store', 'Thumbs.db'];

function is_excluded(string $rel, array $exclude, array $globs): bool
{
    foreach ($exclude as $ex) {
        if (str_ends_with($ex, '/') ? str_starts_with($rel . '/', $ex) || str_starts_with($rel, $ex) : $rel === $ex) {
            return true;
        }
    }
    foreach ($globs as $g) {
        if (fnmatch($g, basename($rel))) {
            return true;
        }
    }
    return false;
}

@mkdir($root . '/dist');
$zipPath = $root . '/dist/fireprodukt-upload-' . date('Ymd-Hi') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Не мога да създам $zipPath\n");
    exit(1);
}
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$added = [];
$skipped = [];
foreach ($it as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (is_excluded($rel, $exclude, $excludeGlobs)) {
        $skipped[] = $rel;
        continue;
    }
    $zip->addFile($file->getPathname(), $rel);
    $added[] = $rel;
}
// празната папка config/ трябва да съществува на сървъра (инсталаторът пише в нея)
$zip->addEmptyDir('config');
$zip->close();

sort($added);
echo "Архив: $zipPath\n";
echo 'Файлове в архива: ' . count($added) . "\n";
echo "Пропуснати:\n";
foreach ($skipped as $s) {
    echo "  - $s\n";
}
