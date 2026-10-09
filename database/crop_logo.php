<?php
$srcPath = __DIR__ . '/../assets/images/logo.jpg';
$destPath = __DIR__ . '/../assets/images/logo.png';

$im = imagecreatefromjpeg($srcPath);
if (!$im) {
    die("Failed to load image");
}

$width = imagesx($im);
$height = imagesy($im);

// Find bounding box of non-pure-white pixels
$minX = $width;
$minY = $height;
$maxX = 0;
$maxY = 0;

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        $rgb = imagecolorat($im, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // If not white (threshold 248)
        if ($r < 248 || $g < 248 || $b < 248) {
            if ($x < $minX) $minX = $x;
            if ($x > $maxX) $maxX = $x;
            if ($y < $minY) $minY = $y;
            if ($y > $maxY) $maxY = $y;
        }
    }
}

// Add padding of 8px
$pad = 8;
$cropX = max(0, $minX - $pad);
$cropY = max(0, $minY - $pad);
$cropW = min($width - $cropX, ($maxX - $minX) + ($pad * 2));
$cropH = min($height - $cropY, ($maxY - $minY) + ($pad * 2));

$cropped = imagecrop($im, [
    'x' => $cropX,
    'y' => $cropY,
    'width' => $cropW,
    'height' => $cropH
]);

if ($cropped) {
    imagepng($cropped, $destPath, 9);
    echo "Cropped successfully to {$cropW}x{$cropH} at assets/images/logo.png\n";
    imagedestroy($cropped);
} else {
    echo "Crop failed\n";
}

imagedestroy($im);
