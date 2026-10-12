<?php
// Makes the outer white background of the site logo transparent (flood fill from edges,
// so white details inside the mark — house windows, gear lines — are preserved).

$path = __DIR__ . '/web/public/logo.png';
$src = imagecreatefrompng($path);
imagepalettetotruecolor($src);
imagealphablending($src, false);
imagesavealpha($src, true);
$w = imagesx($src);
$h = imagesy($src);
$n = $w * $h;

$rgb = [];
for ($i = 0; $i < $n; $i++) {
    $c = imagecolorat($src, $i % $w, intdiv($i, $w));
    $a = ($c >> 24) & 0x7F;
    $rgb[$i] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, $a];
}

// Flood fill from every border pixel that is already transparent or near-white
$removed = array_fill(0, $n, false);
$queue = [];
for ($x = 0; $x < $w; $x++) { $queue[] = $x; $queue[] = ($h - 1) * $w + $x; }
for ($y = 0; $y < $h; $y++) { $queue[] = $y * $w; $queue[] = $y * $w + $w - 1; }

while ($queue) {
    $idx = array_pop($queue);
    if ($removed[$idx]) continue;
    [$r, $g, $b, $a] = $rgb[$idx];
    if (!($a > 100 || min($r, $g, $b) >= 225)) continue;
    $removed[$idx] = true;
    $x = $idx % $w;
    if ($x > 0) $queue[] = $idx - 1;
    if ($x < $w - 1) $queue[] = $idx + 1;
    if ($idx >= $w) $queue[] = $idx - $w;
    if ($idx < $n - $w) $queue[] = $idx + $w;
}

// Anti-alias seam: near-white pixels touching the removed area get partial alpha
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $idx = $y * $w + $x;
        if ($removed[$idx]) continue;
        [$r, $g, $b, $a] = $rgb[$idx];
        if ($a > 100 || min($r, $g, $b) < 195) continue;
        $touch = ($x > 0 && $removed[$idx - 1]) || ($x < $w - 1 && $removed[$idx + 1])
              || ($y > 0 && $removed[$idx - $w]) || ($y < $h - 1 && $removed[$idx + $w]);
        if (!$touch) continue;
        $rgb[$idx][3] = max(30, min(120, (int) round(127 * (min($r, $g, $b) - 195) / 30)));
    }
}

for ($i = 0; $i < $n; $i++) {
    [$r, $g, $b, $a] = $rgb[$i];
    if ($removed[$i]) $a = 127;
    imagesetpixel($src, $i % $w, intdiv($i, $w), imagecolorallocatealpha($src, $r, $g, $b, $a));
}

imagepng($src, $path, 9);
imagedestroy($src);
echo "logo.png background removed\n";
