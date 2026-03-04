<?php
/**
 * AIScannerFactory — returns the correct scanner based on AI_SCANNER_MODE.
 *
 * This is the ONLY file the rest of the app should use to get a scanner.
 * Everything else calls: $scanner = AIScannerFactory::create();
 *
 * Switching between mock and live is a single config change:
 *   AI_SCANNER_MODE=mock  →  AIScannerMock  (Phases 0-3)
 *   AI_SCANNER_MODE=live  →  AIScanner      (Phase 4+)
 *
 * Both classes implement the same scanDocument() method and return
 * the exact same response shape, so no other code needs to change.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/AIScannerMock.php';

class AIScannerFactory
{
    /**
     * Create the appropriate scanner instance.
     *
     * @return AIScannerMock|AIScanner
     */
    public static function create(): object
    {
        if (AI_SCANNER_MODE === 'live') {
            // Only require the real AIScanner when actually needed
            // (avoids loading the Google Cloud SDK in mock mode)
            require_once __DIR__ . '/AIScanner.php';
            return new AIScanner();
        }

        return new AIScannerMock();
    }
}