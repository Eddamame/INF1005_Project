<?php
/**
 * AIScanner — Google Cloud Document AI integration.
 *
 * Uses GCP's pre-trained Receipt Parser, Invoice Parser, or Expense Parser
 * to extract structured data from uploaded vet receipts and reports.
 *
 * Requirements:
 *   composer require google/cloud-document-ai
 *   GCP service account with "Document AI API User" role
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/constants.php';

use Google\Cloud\DocumentAI\V1\Client\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Google\Cloud\DocumentAI\V1\Document;

class AIScanner
{
    private DocumentProcessorServiceClient $client;
    private string $projectId;
    private string $location;

    public function __construct(?string $credentialsPath = null)
    {
        $this->projectId = GCP_PROJECT_ID;
        $this->location  = GCP_LOCATION;

        // On GCE with a bound service account, use ADC (no key file needed).
        // Locally, fall back to GCP_CREDENTIALS_PATH (config/gcp-credentials.json).
        $clientOptions = [];
        $resolvedCredentials = $credentialsPath ?? (IS_PRODUCTION ? null : GCP_CREDENTIALS_PATH);
        if ($resolvedCredentials !== null && file_exists($resolvedCredentials)) {
            $clientOptions['credentials'] = $resolvedCredentials;
        }

        $this->client = new DocumentProcessorServiceClient($clientOptions);
    }

    /**
     * Scan a document and extract structured data.
     *
     * @param  string $filePath  Absolute path to the uploaded file.
     * @param  string $fileType  'receipt' | 'vet_report' | 'invoice' | 'other'
     * @return array  Standardized result with parsed_data, confidence, etc.
     */
    public function scanDocument(string $filePath, string $fileType = 'receipt'): array
    {
        try {
            if (!file_exists($filePath) || !is_readable($filePath)) {
                return $this->errorResponse('File not found or not readable: ' . $filePath);
            }

            // Select the correct GCP processor
            $processorId   = GCP_PROCESSOR_MAP[$fileType] ?? GCP_RECEIPT_PROCESSOR_ID;
            $processorName = $this->client->processorName(
                $this->projectId,
                $this->location,
                $processorId
            );

            // Build and send the request
            $rawDocument = (new RawDocument())
                ->setContent(file_get_contents($filePath))
                ->setMimeType(mime_content_type($filePath));

            $request = (new ProcessRequest())
                ->setName($processorName)
                ->setRawDocument($rawDocument);

            $response = $this->client->processDocument($request);
            $document = $response->getDocument();

            // Extract and normalize
            $rawEntities = $this->extractEntities($document);
            $parsedData  = $this->normalizeForPetInsurance($rawEntities, $fileType);
            $fullText    = $document->getText();
            $confidence  = $this->calculateAverageConfidence($rawEntities);

            return [
                'success'        => true,
                'raw_response'   => $rawEntities,
                'parsed_data'    => $parsedData,
                'entities'       => $rawEntities,
                'full_text'      => $fullText,
                'confidence'     => $confidence,
                'processor_used' => GCP_PROCESSOR_NAMES[$fileType] ?? 'RECEIPT_PARSER',
                'error'          => null,
            ];

        } catch (\Google\ApiCore\ApiException $e) {
            error_log('Document AI API error: ' . $e->getMessage());
            return $this->errorResponse('Document AI API error: ' . $e->getBasicMessage());
        } catch (\Exception $e) {
            error_log('AIScanner error: ' . $e->getMessage());
            return $this->errorResponse('Scanning failed: ' . $e->getMessage());
        }
    }

    /**
     * Extract all entities from the Document AI Document into a flat array.
     */
    private function extractEntities(Document $document): array
    {
        $entities = [];

        foreach ($document->getEntities() as $entity) {
            $entry = [
                'type'       => $entity->getType(),
                'value'      => $entity->getMentionText(),
                'confidence' => round($entity->getConfidence(), 4),
            ];

            // Extract normalized values (MoneyValue, DateValue)
            $normalizedValue = $entity->getNormalizedValue();
            if ($normalizedValue) {
                if ($normalizedValue->getMoneyValue()) {
                    $money = $normalizedValue->getMoneyValue();
                    $entry['normalized_value'] = [
                        'amount'   => $money->getUnits() + ($money->getNanos() / 1e9),
                        'currency' => $money->getCurrencyCode(),
                    ];
                }
                if ($normalizedValue->getDateValue()) {
                    $date = $normalizedValue->getDateValue();
                    $entry['normalized_value'] = sprintf(
                        '%04d-%02d-%02d',
                        $date->getYear(), $date->getMonth(), $date->getDay()
                    );
                }
                if ($normalizedValue->getText() && !isset($entry['normalized_value'])) {
                    $entry['normalized_value'] = $normalizedValue->getText();
                }
            }

            // Handle nested properties (line_item sub-entities)
            $properties = [];
            foreach ($entity->getProperties() as $prop) {
                $propEntry = [
                    'type'       => $prop->getType(),
                    'value'      => $prop->getMentionText(),
                    'confidence' => round($prop->getConfidence(), 4),
                ];
                $propNorm = $prop->getNormalizedValue();
                if ($propNorm && $propNorm->getMoneyValue()) {
                    $money = $propNorm->getMoneyValue();
                    $propEntry['normalized_value'] = [
                        'amount'   => $money->getUnits() + ($money->getNanos() / 1e9),
                        'currency' => $money->getCurrencyCode(),
                    ];
                }
                $properties[] = $propEntry;
            }

            if (!empty($properties)) {
                $entry['properties'] = $properties;
            }

            $entities[] = $entry;
        }

        return $entities;
    }

    /**
     * Normalize Document AI entities into our pet insurance claim schema.
     */
    private function normalizeForPetInsurance(array $rawEntities, string $fileType): array
    {
        $data = [
            'clinic_name'    => null,
            'clinic_code'    => null,
            'clinic_address' => null,
            'clinic_phone'   => null,
            'visit_date'     => null,
            'pet_name'       => null,
            'line_items'     => [],
            'subtotal'       => null,
            'tax'            => null,
            'total'          => null,
            'currency'       => null,
            'invoice_number' => null,
            'payment_method' => null,
        ];

        // Map Document AI entity types → our fields
        $fieldMap = [
            'merchant_name'         => 'clinic_name',
            'supplier_name'         => 'clinic_name',
            'merchant_address'      => 'clinic_address',
            'supplier_address'      => 'clinic_address',
            'merchant_phone_number' => 'clinic_phone',
            'supplier_phone'        => 'clinic_phone',
            'receipt_date'          => 'visit_date',
            'invoice_date'          => 'visit_date',
            'total_amount'          => 'total',
            'net_amount'            => 'subtotal',
            'subtotal'              => 'subtotal',
            'total_tax_amount'      => 'tax',
            'tax_amount'            => 'tax',
            'currency'              => 'currency',
            'currency_code'         => 'currency',
            'invoice_id'            => 'invoice_number',
            'payment_method'        => 'payment_method',
            'transaction_id'        => 'invoice_number',
            'customer_id'           => 'clinic_code',
        ];

        foreach ($rawEntities as $entity) {
            $type = $entity['type'];

            // Handle line items
            if ($type === 'line_item' && isset($entity['properties'])) {
                $lineItem = [
                    'description' => null,
                    'quantity'    => 1,
                    'unit_price'  => null,
                    'amount'      => null,
                ];
                foreach ($entity['properties'] as $prop) {
                    switch ($prop['type']) {
                        case 'line_item/description':
                            $lineItem['description'] = $prop['value'];
                            break;
                        case 'line_item/quantity':
                            $lineItem['quantity'] = (int) $prop['value'];
                            break;
                        case 'line_item/unit_price':
                            $lineItem['unit_price'] = $this->extractAmount($prop);
                            break;
                        case 'line_item/amount':
                            $lineItem['amount'] = $this->extractAmount($prop);
                            break;
                    }
                }
                if ($lineItem['amount'] === null && $lineItem['unit_price'] !== null) {
                    $lineItem['amount'] = $lineItem['unit_price'] * $lineItem['quantity'];
                }
                $data['line_items'][] = $lineItem;
                continue;
            }

            // Map simple fields
            if (isset($fieldMap[$type])) {
                $field = $fieldMap[$type];
                if (in_array($field, ['total', 'subtotal', 'tax'])) {
                    $data[$field] = $this->extractAmount($entity);
                } elseif ($field === 'visit_date') {
                    $data[$field] = $entity['normalized_value'] ?? $entity['value'];
                } else {
                    $data[$field] = $entity['value'];
                }
            }
        }

        // Fallback: extract clinic_code via regex from OCR text
        if ($data['clinic_code'] === null) {
            $data['clinic_code'] = $this->extractClinicCodeFromText($rawEntities);
        }

        // Fallback: calculate total from line items
        if ($data['total'] === null && !empty($data['line_items'])) {
            $data['total'] = array_sum(array_column($data['line_items'], 'amount'));
        }

        return $data;
    }

    private function extractAmount(array $entity): ?float
    {
        if (isset($entity['normalized_value']['amount'])) {
            return (float) $entity['normalized_value']['amount'];
        }
        $cleaned = preg_replace('/[^0-9.]/', '', $entity['value'] ?? '');
        return $cleaned !== '' ? (float) $cleaned : null;
    }

    private function extractClinicCodeFromText(array $entities): ?string
    {
        foreach ($entities as $entity) {
            $value = $entity['value'] ?? '';
            if (preg_match('/\b(VET-[\w-]+|REG-[\w-]+|LIC[\w-]*[\d]+)\b/i', $value, $m)) {
                return strtoupper($m[1]);
            }
        }
        return null;
    }

    private function calculateAverageConfidence(array $entities): float
    {
        if (empty($entities)) return 0.0;
        return round(array_sum(array_column($entities, 'confidence')) / count($entities), 4);
    }

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

    public function __destruct()
    {
        $this->client->close();
    }
}