<?php
/**
 * FileUploadHandler — Secure file upload processing for claim documents.
 *
 * Handles validation, sanitization, and storage of uploaded files.
 * Works with the constants defined in config/constants.php:
 *   - UPLOAD_DIR, MAX_FILE_SIZE, ALLOWED_MIME_TYPES
 *
 * Usage:
 *   require_once __DIR__ . '/../services/FileUploadHandler.php';
 *
 *   $handler = new FileUploadHandler();
 *   $result  = $handler->handle($_FILES['document'], $claimId);
 *
 *   if ($result['success']) {
 *       // $result['file_name'], $result['file_path'], $result['mime_type'], $result['file_size']
 *   } else {
 *       // $result['error']
 *   }
 */

require_once __DIR__ . '/../config/constants.php';

class FileUploadHandler
{
    /**
     * Process and store an uploaded file.
     *
     * @param  array  $file     The $_FILES['field_name'] array.
     * @param  int    $claimId  The claim this document belongs to.
     * @param  string $fileType 'receipt' | 'vet_report' | 'invoice' | 'other'
     * @return array            Result with 'success', file metadata, or 'error'.
     */
    public function handle(array $file, int $claimId, string $fileType = 'receipt'): array
    {
        // ── 1. Check for upload errors ─────────────────────────
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->errorResponse($this->uploadErrorMessage($file['error'] ?? -1));
        }

        // ── 2. Validate file size ──────────────────────────────
        if ($file['size'] > MAX_FILE_SIZE) {
            $maxMB = MAX_FILE_SIZE / (1024 * 1024);
            return $this->errorResponse("File exceeds the maximum size of {$maxMB} MB.");
        }

        if ($file['size'] === 0) {
            return $this->errorResponse('Uploaded file is empty.');
        }

        // ── 3. Validate MIME type (using finfo, not user-supplied type) ──
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
            return $this->errorResponse(
                'File type not allowed. Accepted: JPEG, PNG, WebP, TIFF, PDF.'
            );
        }

        // ── 4. Generate safe filename ──────────────────────────
        $extension   = $this->mimeToExtension($mimeType);
        $safeFileName = sprintf(
            'claim_%d_%s_%s.%s',
            $claimId,
            $fileType,
            bin2hex(random_bytes(8)),
            $extension
        );

        // ── 5. Ensure upload directory exists ──────────────────
        $claimDir = UPLOAD_DIR . $claimId . '/';
        if (!is_dir($claimDir)) {
            if (!mkdir($claimDir, 0755, true)) {
                return $this->errorResponse('Failed to create upload directory.');
            }
        }

        // ── 6. Move file to final location ─────────────────────
        $destPath = $claimDir . $safeFileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return $this->errorResponse('Failed to store uploaded file.');
        }

        // ── 7. Return success with metadata ────────────────────
        return [
            'success'        => true,
            'file_name'      => $safeFileName,
            'original_name'  => basename($file['name']),
            'file_path'      => $destPath,
            'relative_path'  => 'uploads/' . $claimId . '/' . $safeFileName,
            'mime_type'      => $mimeType,
            'file_size'      => $file['size'],
            'file_type'      => $fileType,
            'error'          => null,
        ];
    }

    /**
     * Map MIME types to file extensions.
     */
    private function mimeToExtension(string $mimeType): string
    {
        $map = [
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/webp'       => 'webp',
            'image/tiff'       => 'tiff',
            'application/pdf'  => 'pdf',
        ];

        return $map[$mimeType] ?? 'bin';
    }

    /**
     * Translate PHP upload error codes to human-readable messages.
     */
    private function uploadErrorMessage(int $code): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
        ];

        return $messages[$code] ?? 'Unknown upload error.';
    }

    /**
     * Standardized error response.
     */
    private function errorResponse(string $message): array
    {
        return [
            'success'        => false,
            'file_name'      => null,
            'original_name'  => null,
            'file_path'      => null,
            'relative_path'  => null,
            'mime_type'      => null,
            'file_size'      => null,
            'file_type'      => null,
            'error'          => $message,
        ];
    }
}