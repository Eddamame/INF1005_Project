<?php
/**
 * QuoteEngine — calculates claim payout quotes from AI-parsed vet receipts.
 *
 * Updated workflow:
 *   1. Document is uploaded + scanned by Document AI → parsed_data + confidence
 *   2. QuoteEngine reads the plan, applies deductible + coverage_pct
 *   3. Based on AI scan confidence vs AUTO_APPROVE_CONFIDENCE_THRESHOLD:
 *        - >= threshold → quote status = 'approved', claim status = 'approved'
 *        - <  threshold → quote status = 'rejected', claim status = 'rejected'
 *   4. Admin can still flag/suspend claims after the fact if needed.
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
     * Auto-approves or auto-rejects based on AI confidence.
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

            // Get the confidence from the latest completed scan document
            $confidence = $this->loadLatestConfidence($claimId);

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

            // Confidence is already gated at the scan stage (scan-receipt.php).
            // If a claim reaches quote generation, it means confidence >= threshold,
            // so quotes are always auto-approved here.
            $threshold = defined('AUTO_APPROVE_CONFIDENCE_THRESHOLD')
                ? AUTO_APPROVE_CONFIDENCE_THRESHOLD
                : 0.80;

            // Safety check: verify confidence still meets threshold
            // (in case claim was manually moved to 'scanned' by admin)
            $autoApproved = $confidence >= $threshold;
            $quoteStatus  = $autoApproved ? 'approved' : 'rejected';
            $claimStatus  = $autoApproved ? 'approved' : 'rejected';

            $quoteId = $this->persistQuote($claimId, $calc, $aiData, $plan, $quoteStatus);

            // Update claim status to match
            $upd = $this->db->prepare('UPDATE claims SET status = :status WHERE id = :id');
            $upd->execute([':status' => $claimStatus, ':id' => $claimId]);

            // Audit log entry for auto-decision
            $this->logAutoDecision($claimId, $quoteId, $confidence, $threshold, $autoApproved);

            return [
                'success'        => true,
                'quote_id'       => $quoteId,
                'reference_id'   => $calc['reference_id'],
                'claim_id'       => $claimId,
                'total_vet_cost' => $calc['total_vet_cost'],
                'deductible'     => $calc['deductible'],
                'coverage_pct'   => $calc['coverage_pct'],
                'covered_amount' => $calc['covered_amount'],
                'customer_pays'  => $calc['customer_pays'],
                'line_items'     => $calc['line_items'],
                'auto_approved'  => $autoApproved,
                'confidence'     => $confidence,
                'quote_status'   => $quoteStatus,
                'claim_status'   => $claimStatus,
                'plan'           => [
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
     * Load the AI confidence score from the latest completed scan document.
     */
    private function loadLatestConfidence(int $claimId): float
    {
        $stmt = $this->db->prepare('
            SELECT ai_confidence
            FROM claim_documents
            WHERE claim_id = :cid
              AND scan_status = \'completed\'
            ORDER BY uploaded_at DESC
            LIMIT 1
        ');
        $stmt->execute([':cid' => $claimId]);
        $row = $stmt->fetch();

        return $row ? (float) ($row['ai_confidence'] ?? 0.0) : 0.0;
    }

    /**
     * Core payout math.
     */
    private function calculatePayout(
        ?float $aiTotal,
        array $aiLineItems,
        float $deductible,
        float $coveragePct,
        float $annualLimit
    ): array {
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

        $effectiveDeductible = min($deductible, $totalVetCost);
        $afterDeductible     = max(0.0, $totalVetCost - $effectiveDeductible);

        $coverageFraction = max(0.0, min(1.0, $coveragePct / 100.0));
        $coveredAmount    = $afterDeductible * $coverageFraction;

        if ($annualLimit > 0 && $coveredAmount > $annualLimit) {
            $coveredAmount = $annualLimit;
        }

        $customerPays = $totalVetCost - $coveredAmount;

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
     * Now accepts a status parameter for auto-approve/reject.
     */
    private function persistQuote(
        int $claimId,
        array $calc,
        array $aiData,
        array $plan,
        string $quoteStatus = 'draft'
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
                status,
                reviewed_at
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
                :status,
                :reviewed_at
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
            ':clinic_verified'=> 0,
            ':status'         => $quoteStatus,
            ':reviewed_at'    => ($quoteStatus !== 'draft') ? date('Y-m-d H:i:s') : null,
        ]);

        $quoteId = (int) $this->db->query('SELECT LAST_INSERT_ID()')->fetchColumn();

        return $quoteId;
    }

    /**
     * Log the auto-approval/rejection decision to audit_log.
     */
    private function logAutoDecision(
        int $claimId,
        int $quoteId,
        float $confidence,
        float $threshold,
        bool $approved
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (NULL, :action, 'claim', :eid, :details, :ip)
            ");
            $stmt->execute([
                ':action'  => $approved ? 'auto_approve' : 'auto_reject',
                ':eid'     => $claimId,
                ':details' => json_encode([
                    'quote_id'   => $quoteId,
                    'confidence' => round($confidence, 4),
                    'threshold'  => $threshold,
                    'decision'   => $approved ? 'approved' : 'rejected',
                ]),
                ':ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('QuoteEngine::logAutoDecision error: ' . $e->getMessage());
        }
    }

    private function error(string $message): array
    {
        return [
            'success' => false,
            'error'   => $message,
        ];
    }
}