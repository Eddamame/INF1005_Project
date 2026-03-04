<?php
/**
 * AIScannerMock — Returns realistic fake Document AI responses.
 *
 * Drop-in replacement for AIScanner.php during Phases 0-3.
 * Returns the EXACT same response shape as the real AIScanner so the
 * entire pipeline (QuoteEngine, ClinicVerifier, dashboard views) works
 * end-to-end without GCP credentials.
 *
 * Three scenarios are included to exercise different code paths:
 *   1. Clean receipt   (high confidence, all fields, clinic matches seed data)
 *   2. Partial receipt  (medium confidence, missing fields, no clinic match)
 *   3. Poor scan        (low confidence, garbled text, tests error UI)
 *
 * The scenario is selected deterministically based on the file, so
 * the same test file always produces the same output (reproducible debugging).
 */

require_once __DIR__ . '/../config/constants.php';

class AIScannerMock
{
    /**
     * Simulate a Document AI scan.
     *
     * @param  string $filePath   Absolute path to the uploaded file.
     * @param  string $fileType   'receipt' | 'vet_report' | 'invoice' | 'other'
     * @return array              Same shape as AIScanner::scanDocument()
     */
    public function scanDocument(string $filePath, string $fileType = 'receipt'): array
    {
        // Simulate network + processing latency (helps test loading states in the UI)
        usleep(800000); // 0.8 seconds

        if (!file_exists($filePath)) {
            return $this->errorResponse('File not found: ' . $filePath);
        }

        $scenario = $this->selectScenario($filePath, $fileType);

        return [
            'success'        => true,
            'raw_response'   => $scenario['entities'],
            'parsed_data'    => $scenario['parsed_data'],
            'entities'       => $scenario['entities'],
            'full_text'      => $scenario['full_text'],
            'confidence'     => $scenario['confidence'],
            'processor_used' => GCP_PROCESSOR_NAMES[$fileType] ?? 'RECEIPT_PARSER',
            'error'          => null,
        ];
    }

    /**
     * Select a scenario deterministically based on file properties.
     * Same file → same scenario every time (reproducible testing).
     */
    private function selectScenario(string $filePath, string $fileType): array
    {
        $scenarios = $this->getScenarios();
        $keys      = array_keys($scenarios);
        $index     = crc32($fileType . filesize($filePath)) % count($keys);

        return $scenarios[$keys[$index]];
    }

    /**
     * All mock scenarios.
     * Each returns data in the exact shape that normalizeForPetInsurance() produces.
     */
    private function getScenarios(): array
    {
        return [

            // ─────────────────────────────────────────────
            // SCENARIO 1: Clean receipt, high confidence
            // Clinic code VET-HP-2024 MATCHES seed data
            // ─────────────────────────────────────────────
            'clean_receipt' => [
                'parsed_data' => [
                    'clinic_name'    => 'Happy Paws Veterinary Clinic',
                    'clinic_code'    => 'VET-HP-2024',
                    'clinic_address' => '123 Pet Street, Animalville, AN 12345',
                    'clinic_phone'   => '(555) 123-4567',
                    'visit_date'     => '2026-02-28',
                    'pet_name'       => null,
                    'line_items'     => [
                        [
                            'description' => 'General Consultation',
                            'quantity'    => 1,
                            'unit_price'  => 75.00,
                            'amount'      => 75.00,
                        ],
                        [
                            'description' => 'X-Ray (Chest)',
                            'quantity'    => 1,
                            'unit_price'  => 250.00,
                            'amount'      => 250.00,
                        ],
                        [
                            'description' => 'Amoxicillin 250mg x14',
                            'quantity'    => 1,
                            'unit_price'  => 45.00,
                            'amount'      => 45.00,
                        ],
                    ],
                    'subtotal'       => 370.00,
                    'tax'            => 0.00,
                    'total'          => 370.00,
                    'currency'       => 'USD',
                    'invoice_number' => 'INV-2026-0482',
                    'payment_method' => 'Visa ****1234',
                ],
                'entities' => [
                    [
                        'type'       => 'merchant_name',
                        'value'      => 'Happy Paws Veterinary Clinic',
                        'confidence' => 0.9700,
                    ],
                    [
                        'type'       => 'merchant_address',
                        'value'      => '123 Pet Street, Animalville, AN 12345',
                        'confidence' => 0.9400,
                    ],
                    [
                        'type'             => 'receipt_date',
                        'value'            => '02/28/2026',
                        'confidence'       => 0.9900,
                        'normalized_value' => '2026-02-28',
                    ],
                    [
                        'type'             => 'total_amount',
                        'value'            => '$370.00',
                        'confidence'       => 0.9900,
                        'normalized_value' => ['amount' => 370.00, 'currency' => 'USD'],
                    ],
                    [
                        'type'       => 'line_item',
                        'value'      => 'General Consultation $75.00',
                        'confidence' => 0.9600,
                        'properties' => [
                            ['type' => 'line_item/description', 'value' => 'General Consultation', 'confidence' => 0.9600],
                            ['type' => 'line_item/amount', 'value' => '$75.00', 'confidence' => 0.9800, 'normalized_value' => ['amount' => 75.00, 'currency' => 'USD']],
                        ],
                    ],
                    [
                        'type'       => 'line_item',
                        'value'      => 'X-Ray (Chest) $250.00',
                        'confidence' => 0.9500,
                        'properties' => [
                            ['type' => 'line_item/description', 'value' => 'X-Ray (Chest)', 'confidence' => 0.9500],
                            ['type' => 'line_item/amount', 'value' => '$250.00', 'confidence' => 0.9700, 'normalized_value' => ['amount' => 250.00, 'currency' => 'USD']],
                        ],
                    ],
                    [
                        'type'       => 'line_item',
                        'value'      => 'Amoxicillin 250mg x14 $45.00',
                        'confidence' => 0.9300,
                        'properties' => [
                            ['type' => 'line_item/description', 'value' => 'Amoxicillin 250mg x14', 'confidence' => 0.9300],
                            ['type' => 'line_item/amount', 'value' => '$45.00', 'confidence' => 0.9600, 'normalized_value' => ['amount' => 45.00, 'currency' => 'USD']],
                        ],
                    ],
                    [
                        'type'       => 'payment_method',
                        'value'      => 'Visa ****1234',
                        'confidence' => 0.8800,
                    ],
                ],
                'full_text' => implode("\n", [
                    'HAPPY PAWS VETERINARY CLINIC',
                    '123 Pet Street, Animalville, AN 12345',
                    'Tel: (555) 123-4567',
                    'VET-HP-2024',
                    '',
                    'Date: 02/28/2026',
                    'Invoice: INV-2026-0482',
                    '',
                    'General Consultation         $75.00',
                    'X-Ray (Chest)               $250.00',
                    'Amoxicillin 250mg x14        $45.00',
                    '',
                    'Total:                      $370.00',
                    'Paid: Visa ****1234',
                ]),
                'confidence' => 0.9544,
            ],

            // ─────────────────────────────────────────────
            // SCENARIO 2: Partial data, medium confidence
            // No clinic code — verification will FAIL
            // ─────────────────────────────────────────────
            'partial_receipt' => [
                'parsed_data' => [
                    'clinic_name'    => 'City Vet Center',
                    'clinic_code'    => null,
                    'clinic_address' => null,
                    'clinic_phone'   => null,
                    'visit_date'     => '2026-03-01',
                    'pet_name'       => null,
                    'line_items'     => [
                        [
                            'description' => 'Emergency Visit',
                            'quantity'    => 1,
                            'unit_price'  => null,
                            'amount'      => 150.00,
                        ],
                        [
                            'description' => 'Blood Test',
                            'quantity'    => 1,
                            'unit_price'  => null,
                            'amount'      => 85.00,
                        ],
                    ],
                    'subtotal'       => null,
                    'tax'            => null,
                    'total'          => 235.00,
                    'currency'       => 'USD',
                    'invoice_number' => null,
                    'payment_method' => null,
                ],
                'entities' => [
                    ['type' => 'merchant_name', 'value' => 'City Vet Center',  'confidence' => 0.7800],
                    ['type' => 'receipt_date',  'value' => '03/01/2026',       'confidence' => 0.8200, 'normalized_value' => '2026-03-01'],
                    ['type' => 'total_amount',  'value' => '$235.00',          'confidence' => 0.8500, 'normalized_value' => ['amount' => 235.00, 'currency' => 'USD']],
                    [
                        'type' => 'line_item', 'value' => 'Emergency Visit $150.00', 'confidence' => 0.7500,
                        'properties' => [
                            ['type' => 'line_item/description', 'value' => 'Emergency Visit', 'confidence' => 0.7500],
                            ['type' => 'line_item/amount',      'value' => '$150.00',         'confidence' => 0.8000, 'normalized_value' => ['amount' => 150.00, 'currency' => 'USD']],
                        ],
                    ],
                    [
                        'type' => 'line_item', 'value' => 'Blood Test $85.00', 'confidence' => 0.7300,
                        'properties' => [
                            ['type' => 'line_item/description', 'value' => 'Blood Test', 'confidence' => 0.7300],
                            ['type' => 'line_item/amount',      'value' => '$85.00',      'confidence' => 0.7900, 'normalized_value' => ['amount' => 85.00, 'currency' => 'USD']],
                        ],
                    ],
                ],
                'full_text'  => "City Vet Center\n\nDate: 03/01/2026\n\nEmergency Visit   $150.00\nBlood Test         $85.00\n\nTotal: $235.00",
                'confidence' => 0.7883,
            ],

            // ─────────────────────────────────────────────
            // SCENARIO 3: Poor scan, low confidence
            // Tests UI warning states and admin review flow
            // ─────────────────────────────────────────────
            'poor_scan' => [
                'parsed_data' => [
                    'clinic_name'    => 'Anml Hsptl',
                    'clinic_code'    => null,
                    'clinic_address' => null,
                    'clinic_phone'   => null,
                    'visit_date'     => null,
                    'pet_name'       => null,
                    'line_items'     => [],
                    'subtotal'       => null,
                    'tax'            => null,
                    'total'          => 120.00,
                    'currency'       => null,
                    'invoice_number' => null,
                    'payment_method' => null,
                ],
                'entities' => [
                    ['type' => 'merchant_name', 'value' => 'Anml Hsptl', 'confidence' => 0.4200],
                    ['type' => 'total_amount',  'value' => '120.00',      'confidence' => 0.5500, 'normalized_value' => ['amount' => 120.00, 'currency' => 'USD']],
                ],
                'full_text'  => "Anml Hsptl\n... [illegible] ...\nTtl: 120.00",
                'confidence' => 0.4850,
            ],
        ];
    }

    /**
     * Standardized error response (same shape as AIScanner).
     */
    private function errorResponse(string $message): array
    {
        return [
            'success'        => false,
            'raw_response'   => [],
            'parsed_data'    => [],
            'entities'       => [],
            'full_text'      => '',
            'confidence'     => 0.0,
            'processor_used' => null,
            'error'          => $message,
        ];
    }
}