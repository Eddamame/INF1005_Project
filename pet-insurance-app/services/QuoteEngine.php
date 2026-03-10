<?php
/**
 * QuoteEngine — calculates claim payout quotes from AI-parsed vet receipts.
 *
 * Data flow (Phase 1+):
 *   1. Document is uploaded + scanned by Document AI → parsed_data
 *   2. A Claim is created and linked to one or more ClaimDocuments.
 *   3. QuoteEngine reads:
 *        - The customer's active subscription/plan
 *        - The AI-parsed monetary data (total, line_items)
 *   4. It applies: deductible + coverage_pct → computes:
 *        - total_vet_cost
 *        - deductible
 *        - coverage_pct
 *        - covered_amount
 *        - customer_pays
 *        - line_items (JSON payload suitable for UI)
 *
 * Usage (simple):
 *   require_once __DIR__ . '/../services/QuoteEngine.php';
 *   $engine = new QuoteEngine($db);
 *   $quote  = $engine->generateForClaim($claimId);
 *
 *   if ($quote['success']) {
 *       // $quote['quote_id'], $quote['reference_id'], etc.
 *   } else {
 *       // $quote['error']
 *   }
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../services/ReferenceGenerator.php';

class QuoteEngine
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate (or regenerate) a quote for a given claim.
     *
     * @param  int $claimId
     * @return array  ['success' => bool, ...]
     */
    public function generateForClaim(int $claimId): array
    {
        try {
            $claim = $this->loadClaimWithContext($claimId);
            if (!$claim) {
                return $this->error('Claim not found or not eligible for quoting.');
            }

            $plan = $this->loadPlanForSubscription((int) $claim['subscription_id']);
            if (!$plan) {
                return $this->error('No insurance plan found for this subscription.');
            }

            $aiData = $this->loadLatestParsedData($claimId);
            if (!$aiData) {
                return $this->error('No AI scan data available for this claim.');
            }

            $calc = $this->calculatePayout(
                $aiData['total'] ?? null,
                $aiData['line_items'] ?? [],
                (float) $plan['deductible'],
                (float) $plan['coverage_pct'],
                (float) $plan['annual_limit']
            );

            if (!$calc['success']) {
                return $calc;
            }

            $quoteId = $this->persistQuote($claimId, $calc, $aiData, $plan);

            return [
                'success'       => true,
                'quote_id'      => $quoteId,
                'reference_id'  => $calc['reference_id'],
                'claim_id'      => $claimId,
                'total_vet_cost'=> $calc['total_vet_cost'],
                'deductible'    => $calc['deductible'],
                'coverage_pct'  => $calc['coverage_pct'],
                'covered_amount'=> $calc['covered_amount'],
                'customer_pays' => $calc['customer_pays'],
                'line_items'    => $calc['line_items'],
                'plan'          => [
                    'id'             => (int) $plan['id'],
                    'name'           => $plan['name'],
                    'annual_limit'   => (float) $plan['annual_limit'],
                    'coverage_pct'   => (float) $plan['coverage_pct'],
                    'deductible'     => (float) $plan['deductible'],
                ],
            ];
        } catch (Throwable $e) {
            error_log('QuoteEngine::generateForClaim error: ' . $e->getMessage());
            return $this->error('An unexpected error occurred while generating the quote.');
        }
    }

    /**
     * Load claim and verify it is in a quote-able state.
     */
    private function loadClaimWithContext(int $claimId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT c.*
            FROM claims c
            WHERE c.id = :id
              AND c.status IN (\'scanned\', \'verified\', \'quoted\')
            LIMIT 1
        ');
        $stmt->execute([':id' => $claimId]);
        return $stmt->fetch();
    }

    /**
     * Load the insurance plan associated with a subscription.
     */
    private function loadPlanForSubscription(int $subscriptionId): array|false
    {
        $stmt = $this->db->prepare('
            SELECT p.*
            FROM subscriptions s
            JOIN insurance_plans p ON p.id = s.plan_id
            WHERE s.id = :sid
            LIMIT 1
        ');
        $stmt->execute([':sid' => $subscriptionId]);
        return $stmt->fetch();
    }

    /**
     * Load the latest completed AI parsed_data for the claim.
     */
    private function loadLatestParsedData(int $claimId): ?array
    {
        $stmt = $this->db->prepare('
            SELECT ai_parsed_data
            FROM claim_documents
            WHERE claim_id = :cid
              AND scan_status = \'completed\'
            ORDER BY uploaded_at DESC
            LIMIT 1
        ');
        $stmt->execute([':cid' => $claimId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $data = json_decode($row['ai_parsed_data'] ?? '[]', true);
        return is_array($data) ? $data : null;
    }

    /**
     * Core payout math.
     *
     * @param  float|null $aiTotal
     * @param  array      $aiLineItems
     * @param  float      $deductible
     * @param  float      $coveragePct
     * @param  float      $annualLimit
     * @return array
     */
    private function calculatePayout(
        ?float $aiTotal,
        array $aiLineItems,
        float $deductible,
        float $coveragePct,
        float $annualLimit
    ): array {
        // Fallback: recompute total from line items if needed
        if ($aiTotal === null) {
            $sum = 0.0;
            foreach ($aiLineItems as $item) {
                $amount = (float) ($item['amount'] ?? 0);
                $sum   += $amount;
            }
            $aiTotal = $sum;
        }

        if ($aiTotal <= 0) {
            return $this->error('AI did not extract a valid total amount from the receipt.');
        }

        $totalVetCost = $aiTotal;

        // Apply deductible (cannot exceed total)
        $effectiveDeductible = min($deductible, $totalVetCost);
        $afterDeductible     = max(0.0, $totalVetCost - $effectiveDeductible);

        // Apply coverage percentage
        $coverageFraction = max(0.0, min(1.0, $coveragePct / 100.0));
        $coveredAmount    = $afterDeductible * $coverageFraction;

        // Apply annual limit
        if ($annualLimit > 0 && $coveredAmount > $annualLimit) {
            $coveredAmount = $annualLimit;
        }

        $customerPays = $totalVetCost - $coveredAmount;

        // Normalize line items for JSON storage
        $normalizedItems = [];
        foreach ($aiLineItems as $item) {
            $normalizedItems[] = [
                'description' => (string) ($item['description'] ?? ''),
                'quantity'    => (float)  ($item['quantity']    ?? 1),
                'amount'      => (float)  ($item['amount']      ?? 0),
            ];
        }

        $referenceId = ReferenceGenerator::uniqueQuote($this->db);

        return [
            'success'        => true,
            'reference_id'   => $referenceId,
            'total_vet_cost' => round($totalVetCost, 2),
            'deductible'     => round($effectiveDeductible, 2),
            'coverage_pct'   => round($coveragePct, 2),
            'covered_amount' => round($coveredAmount, 2),
            'customer_pays'  => round($customerPays, 2),
            'line_items'     => $normalizedItems,
        ];
    }

    /**
     * Persist quote row and return its ID.
     */
    private function persistQuote(
        int $claimId,
        array $calc,
        array $aiData,
        array $plan
    ): int {
        $stmt = $this->db->prepare('
            INSERT INTO quotes (
                reference_id,
                claim_id,
                total_vet_cost,
                deductible,
                coverage_pct,
                covered_amount,
                customer_pays,
                line_items,
                clinic_verified,
                status
            ) VALUES (
                :ref,
                :claim_id,
                :total_vet_cost,
                :deductible,
                :coverage_pct,
                :covered_amount,
                :customer_pays,
                :line_items,
                :clinic_verified,
                :status
            )
        ');

        $stmt->execute([
            ':ref'            => $calc['reference_id'],
            ':claim_id'       => $claimId,
            ':total_vet_cost' => $calc['total_vet_cost'],
            ':deductible'     => $calc['deductible'],
            ':coverage_pct'   => $calc['coverage_pct'],
            ':covered_amount' => $calc['covered_amount'],
            ':customer_pays'  => $calc['customer_pays'],
            ':line_items'     => json_encode($calc['line_items']),
            ':clinic_verified'=> 0, // ClinicVerifier may update this later
            ':status'         => 'draft',
        ]);

        // Also mark claim as "quoted"
        $upd = $this->db->prepare('UPDATE claims SET status = :status WHERE id = :id');
        $upd->execute([
            ':status' => 'quoted',
            ':id'     => $claimId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function error(string $message): array
    {
        return [
            'success' => false,
            'error'   => $message,
        ];
    }
}

