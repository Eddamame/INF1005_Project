# Generate sample vet receipt PNGs (Windows, no PHP GD required)
# Run: powershell -ExecutionPolicy Bypass -File tests/fixtures/receipts/generate.ps1
# From pet-insurance-app directory.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$outDir = $PSScriptRoot

function Save-ReceiptPng {
    param(
        [string]$Path,
        [string[]]$Lines,
        [switch]$HeavyBlur
    )
    $w = 720
    $h = [Math]::Min(1100, 60 + $Lines.Count * 24)
    $bmp = New-Object System.Drawing.Bitmap $w, $h
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.Clear([System.Drawing.Color]::White)
    $font = [System.Drawing.Font]::new('Consolas', [float]11.0, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel)
    $brushBlack = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(25, 35, 45))
    $brushGray  = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb(90, 100, 110))
    $y = 36
    $i = 0
    foreach ($line in $Lines) {
        $b = if ($i -eq 0) { $brushBlack } else { $brushGray }
        $g.DrawString($line, $font, $b, 36, $y)
        $y += 24
        $i++
    }
    if ($HeavyBlur) {
        # Simple blur pass: shrink and stretch (readable but degraded)
        $smallW = [int]($w / 3)
        $smallH = [int]($h / 3)
        $tmp = New-Object System.Drawing.Bitmap $smallW, $smallH
        $g2 = [System.Drawing.Graphics]::FromImage($tmp)
        $g2.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::Low
        $g2.DrawImage($bmp, 0, 0, $smallW, $smallH)
        $g2.Dispose()
        $g.Dispose()
        $bmp.Dispose()
        $bmp = New-Object System.Drawing.Bitmap $w, $h
        $g = [System.Drawing.Graphics]::FromImage($bmp)
        $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::Low
        $g.DrawImage($tmp, 0, 0, $w, $h)
        $tmp.Dispose()
        $g.Dispose()
        $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
        $bmp.Dispose()
        return
    }
    $g.Dispose()
    $bmp.Save($Path, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
}

$cleanLines = @(
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
    'Paid: Visa ****1234'
)

$partialLines = @(
    'City Vet Center',
    '',
    'Date: 03/01/2026',
    '',
    'Emergency Visit   $150.00',
    'Blood Test         $85.00',
    '',
    'Total: $235.00'
)

Save-ReceiptPng -Path (Join-Path $outDir '01-vet-receipt-clean.png') -Lines $cleanLines
Save-ReceiptPng -Path (Join-Path $outDir '02-vet-receipt-partial.png') -Lines $partialLines
Save-ReceiptPng -Path (Join-Path $outDir '03-vet-receipt-blurry.png') -Lines $cleanLines -HeavyBlur

Write-Host 'Wrote PNG fixtures to' $outDir
