<?php
/**
 * ReferenceGenerator — Generates unique reference IDs for claims and quotes.
 *
 * Format:
 *   Claims: CLM-20260310-A7F3B2   (CLM + date + 6 hex chars)
 *   Quotes: QUO-20260310-E1D4C8   (QUO + date + 6 hex chars)
 *
 * These are human-readable IDs shown to customers (not the database PK).
 * The hex suffix makes collisions virtually impossible.
 *
 * Usage:
 *   require_once __DIR__ . '/../services/ReferenceGenerator.php';
 *
 *   $claimRef = ReferenceGenerator::claim();   // "CLM-20260310-A7F3B2"
 *   $quoteRef = ReferenceGenerator::quote();   // "QUO-20260310-E1D4C8"
 *
 *   // Or verify uniqueness against the DB:
 *   $claimRef = ReferenceGenerator::uniqueClaim($db);
 *   $quoteRef = ReferenceGenerator::uniqueQuote($db);
 */

class ReferenceGenerator
{
    /**
     * Generate a claim reference ID.
     *
     * @return string  e.g. "CLM-20260310-A7F3B2"
     */
    public static function claim(): string
    {
        return self::generate('CLM');
    }

    /**
     * Generate a quote reference ID.
     *
     * @return string  e.g. "QUO-20260310-E1D4C8"
     */
    public static function quote(): string
    {
        return self::generate('QUO');
    }

    /**
     * Generate a claim reference guaranteed unique in the claims table.
     * Retries up to 10 times if a collision occurs (astronomically unlikely).
     *
     * @param  PDO $db  Database connection.
     * @return string   Unique claim reference.
     * @throws RuntimeException if unable to generate a unique ID.
     */
    public static function uniqueClaim(PDO $db): string
    {
        return self::uniqueFor($db, 'CLM', 'claims');
    }

    /**
     * Generate a quote reference guaranteed unique in the quotes table.
     *
     * @param  PDO $db  Database connection.
     * @return string   Unique quote reference.
     * @throws RuntimeException if unable to generate a unique ID.
     */
    public static function uniqueQuote(PDO $db): string
    {
        return self::uniqueFor($db, 'QUO', 'quotes');
    }

    /**
     * Core generator: PREFIX-YYYYMMDD-XXXXXX
     *
     * @param  string $prefix  'CLM' or 'QUO'
     * @return string
     */
    private static function generate(string $prefix): string
    {
        $date   = date('Ymd');
        $suffix = strtoupper(bin2hex(random_bytes(3))); // 6 hex chars

        return sprintf('%s-%s-%s', $prefix, $date, $suffix);
    }

    /**
     * Generate a reference and verify it doesn't already exist in the given table.
     *
     * @param  PDO    $db     Database connection.
     * @param  string $prefix 'CLM' or 'QUO'
     * @param  string $table  'claims' or 'quotes'
     * @return string         Unique reference ID.
     * @throws RuntimeException
     */
    private static function uniqueFor(PDO $db, string $prefix, string $table): string
    {
        $maxAttempts = 10;

        // Whitelist table name to prevent SQL injection
        $allowedTables = ['claims', 'quotes'];
        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException("Invalid table: {$table}");
        }

        for ($i = 0; $i < $maxAttempts; $i++) {
            $ref  = self::generate($prefix);
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE reference_id = :ref");
            $stmt->execute([':ref' => $ref]);

            if ((int) $stmt->fetchColumn() === 0) {
                return $ref;
            }
        }

        throw new RuntimeException(
            "Failed to generate unique {$prefix} reference after {$maxAttempts} attempts."
        );
    }
}