<?php

/**
 * Generate a .webp sibling for every large raster image under public/.
 *
 *   public/assets/images/header1.png  ->  public/assets/images/header1.png.webp
 *
 * nginx serves the sibling to browsers that advertise WebP support, so the HTML
 * keeps referencing the original file and stays byte-identical to the live site.
 *
 * Usage: php docker/php/make-webp.php [minKB] [quality]
 */

$root = '/var/www/html/public';
$minBytes = ((int) ($argv[1] ?? 100)) * 1024;
$quality = (int) ($argv[2] ?? 82);

$totalBefore = 0;
$totalAfter = 0;
$made = 0;
$skipped = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($it as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $ext = strtolower($file->getExtension());

    if (! in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        continue;
    }

    $size = $file->getSize();
    if ($size < $minBytes) {
        continue;
    }

    $out = $path.'.webp';
    if (is_file($out) && filemtime($out) >= filemtime($path)) {
        $skipped++;
        continue;
    }

    $img = match ($ext) {
        'png' => @imagecreatefrompng($path),
        default => @imagecreatefromjpeg($path),
    };

    if (! $img) {
        fwrite(STDERR, "  FAILED to decode {$path}\n");
        continue;
    }

    if ($ext === 'png') {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
    }

    if (! imagewebp($img, $out, $quality)) {
        fwrite(STDERR, "  FAILED to encode {$out}\n");
        imagedestroy($img);
        continue;
    }
    imagedestroy($img);

    $newSize = filesize($out);
    $totalBefore += $size;
    $totalAfter += $newSize;
    $made++;

    printf(
        "  %-52s %7.1f MB -> %6.1f MB  (-%d%%)\n",
        substr(str_replace($root.'/', '', $path), 0, 52),
        $size / 1048576,
        $newSize / 1048576,
        $size > 0 ? round((1 - $newSize / $size) * 100) : 0
    );
}

printf("\nconverted %d, already current %d\n", $made, $skipped);
if ($totalBefore > 0) {
    printf(
        "total %.1f MB -> %.1f MB  (saved %.1f MB, -%d%%)\n",
        $totalBefore / 1048576,
        $totalAfter / 1048576,
        ($totalBefore - $totalAfter) / 1048576,
        round((1 - $totalAfter / $totalBefore) * 100)
    );
}
