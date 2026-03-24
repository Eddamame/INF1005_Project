<?php
/**
 * ClinicVerifier — cross-checks AI-extracted clinic info against vet_clinics.
 *
 * Responsibilities:
 *   - Take AI parsed_data (clinic_name, clinic_code, address, etc.).
 *   - Look for an exact match on clinic_code when available.
 *   - Fallback to fuzzy-ish matching on name if no code is present.
 *   - Mark whether the clinic is recognized/verified.
 *   - Optionally update the quotes table (clinic_verified flag).
 *
 * Does NOT change claim status directly; it only sets
 * the verification flag on a specific quote, so that admin review
 * can make the final decision.
 */

class ClinicVerifier
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Verify clinic information and update a quote's clinic_verified flag.
     *
     * @param  int   $quoteId
     * @param  array $parsedData  AI parsed_data for this claim document
     * @return array              ['success' => bool, 'verified' => bool, 'clinic' => ?array, 'error' => ?string]
     */
    public function verifyForQuote(int $quoteId, array $parsedData): array
    {
        try {
            $clinic = $this->findClinic($parsedData);
            $verified = $clinic !== null && (int) ($clinic['is_verified'] ?? 0) === 1;
            error_log('ClinicVerifier: verified=' . ($verified ? 'true' : 'false') . ' quoteId=' . $quoteId);

            $stmt = $this->db->prepare('UPDATE quotes SET clinic_verified = :flag WHERE id = :id');
            $stmt->execute([
                ':flag' => $verified ? 1 : 0,
                ':id'   => $quoteId,
            ]);

            return [
                'success'  => true,
                'verified' => $verified,
                'clinic'   => $clinic,
                'error'    => null,
            ];
        } catch (Throwable $e) {
            error_log('ClinicVerifier::verifyForQuote error: ' . $e->getMessage());
            return [
                'success'  => false,
                'verified' => false,
                'clinic'   => null,
                'error'    => 'An error occurred while verifying the clinic.',
            ];
        }
    }

    /**
     * Attempt to find a clinic from parsed data.
     *
     * Priority:
     *   1. Exact clinic_code match.
     *   2. Case-insensitive name match (LIKE with wildcards).
     *
     * @param  array $parsed
     * @return array|null
     */
    public function findClinic(array $parsed): ?array
    {
        // Normalize for reliable matching against seeded DB values
        $code = strtoupper(trim((string) ($parsed['clinic_code'] ?? '')));
        $name = trim((string) ($parsed['clinic_name'] ?? ''));

        // 1. Exact code match (best signal)
        if ($code !== '') {
            $stmt = $this->db->prepare('
                SELECT *
                FROM vet_clinics
                WHERE clinic_code = :code
                LIMIT 1
            ');
            $stmt->execute([':code' => $code]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }
        error_log('ClinicVerifier: code=' . $code . ' name=' . $name);

        // 2. Fuzzy name match — check both directions (DB name contains extracted, or extracted contains DB name)
        if ($name !== '') {
            $stmt = $this->db->prepare('
                SELECT *
                FROM vet_clinics
                WHERE LOWER(name) LIKE LOWER(:name)
                   OR LOWER(:name2) LIKE CONCAT(\'%\', LOWER(name), \'%\')
                ORDER BY is_verified DESC, id ASC
                LIMIT 1
            ');
            $stmt->execute([':name' => '%' . $name . '%', ':name2' => $name]);
            $row = $stmt->fetch();
            error_log('ClinicVerifier: row=' . json_encode($row));
            if ($row) {
                return $row;
            }
        }

        return null;
    }
}

