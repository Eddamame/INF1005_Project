<?php
/**
 * Generate sample vet receipt PNGs for manual / automated testing.
 *
 * Run from project root or this folder:
 *   php tests/fixtures/receipts/generate.php
 *
 * Requires PHP GD extension.
 */
declare(strict_types=1);

$outDir = __DIR__;

if (!extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension is required. Enable extension=gd in php.ini (XAMPP: usually enabled by default).\n");
    exit(1);
}

/**
 * Draw left-aligned lines using built-in font (no TTF needed).
 */
function saveReceiptPng(string $path, array $lines, bool $heavyBlur = false): void
{
    $w = 720;
    $h = min(1100, 60 + count($lines) * 22);
    $im = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 25, 35, 45);
    $gray  = imagecolorallocate($im, 90, 100, 110);
    imagefilledrectangle($im, 0, 0, $w, $h, $white);

    $y = 36;
    foreach ($lines as $i => $line) {
        $color = ($i === 0) ? $black : $gray;
        $chunk = function_exists('mb_substr') ? mb_substr($line, 0, 95, 'UTF-8') : substr($line, 0, 95);
        imagestring($im, 4, 36, $y, $chunk, $color);
        $y += 22;
    }

    if ($heavyBlur) {
        for ($b = 0; $b < 12; $b++) {
            if (defined('IMG_FILTER_GAUSSIAN_BLUR')) {
                imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
            }
        }
        imagefilter($im, IMG_FILTER_BRIGHTNESS, -15);
    }

    imagepng($im, $path, 6);
    imagedestroy($im);
}

$cleanLines = [
    'HAPPY PAWS VETERINARY CLINIC',
    '123 Pet Street, Animalville, AN 12345',
    'Tel: (555) 123-4567',
    'Clinic ID: VET-HP-2024',
    '',
    'Date: 02/28/2026',
    'Invoice: INV-2026-0482',
    '',
    'General Consultation         $75.00',
    'X-Ray (Chest)               $250.00',
    'Amoxicillin 250mg x14        $45.00',
    '',
    'Subtotal:                   $370.00',
    'Total:                      $370.00',
    'Paid: Visa ****1234',
];

$partialLines = [
    'City Vet Center',
    '',
    'Date: 03/01/2026',
    '',
    'Emergency Visit   $150.00',
    'Blood Test         $85.00',
    '',
    'Total: $235.00',
];

saveReceiptPng($outDir . '/01-vet-receipt-clean.png', $cleanLines, false);
saveReceiptPng($outDir . '/02-vet-receipt-partial.png', $partialLines, false);
saveReceiptPng($outDir . '/03-vet-receipt-blurry.png', $cleanLines, true);

echo "Wrote:\n";
echo "  01-vet-receipt-clean.png   (mock: clean / high confidence)\n";
echo "  02-vet-receipt-partial.png (mock: partial / clinic verify may fail)\n";
echo "  03-vet-receipt-blurry.png  (mock: poor scan / low confidence)\n";
