<?php

declare(strict_types=1);

if ($argc !== 3 || preg_match('/^\d+\.\d+\.\d+-hs$/', $argv[1]) !== 1) {
    fwrite(STDERR, "Usage: php dev/build-hs-release.php VERSION OUTPUT_DIR\n");
    exit(2);
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The zip extension is required.\n");
    exit(1);
}
$version = $argv[1];
$root = dirname(__DIR__);
$output = rtrim($argv[2], '/\\');
if (!is_dir($output) && !mkdir($output, 0755, true)) {
    exit(1);
}
$topFiles = [
    'index.php', 'install.php', 'public.php', '.htaccess', 'composer.json', 'composer.lock',
    'LICENSE', 'README.rst', 'CHANGELOG.md', 'CHANGELOG.rst', 'manifest.webmanifest',
    'service-worker.js', 'offline.html', 'favicon.ico', 'favicon.png', 'phpservermon.png',
];
$topDirectories = ['cron', 'bin', 'src', 'vendor', 'docs'];
$files = [];
foreach ($topFiles as $relative) {
    if (is_file($root . DIRECTORY_SEPARATOR . $relative)) {
        $files[] = $relative;
    }
}
foreach ($topDirectories as $relativeDirectory) {
    $directory = $root . DIRECTORY_SEPARATOR . $relativeDirectory;
    if (!is_dir($directory)) { continue; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile() || $file->isLink()) { continue; }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, 'vendor/bin/.phpunit')) { continue; }
        $files[] = $relative;
    }
}
sort($files, SORT_STRING);
$hashes = [];
foreach ($files as $relative) {
    $hash = hash_file('sha256', $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    if (!is_string($hash)) { exit(1); }
    $hashes[$relative] = $hash;
}
$internalManifest = json_encode([
    'version' => $version,
    'files' => $hashes,
    'delete' => [],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
$archiveName = 'phpservermon-' . $version . '.zip';
$archivePath = $output . DIRECTORY_SEPARATOR . $archiveName;
$zip = new ZipArchive();
if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { exit(1); }
foreach ($files as $relative) {
    if (!$zip->addFile($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative), $relative)) { exit(1); }
}
$zip->addFromString('.hs-release.json', $internalManifest);
$zip->close();
$archiveHash = hash_file('sha256', $archivePath);
if (!is_string($archiveHash)) { exit(1); }
$manifest = json_encode([
    'schema' => 1,
    'version' => $version,
    'archive' => $archiveName,
    'sha256' => $archiveHash,
    'min_php' => '8.5.0',
    'repository' => 'RicRey1988/phpservermon',
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
file_put_contents($output . DIRECTORY_SEPARATOR . 'phpservermon-' . $version . '.json', $manifest, LOCK_EX);
