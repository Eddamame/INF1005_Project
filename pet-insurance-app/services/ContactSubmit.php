<?php
/**
 * ContactSubmit — handles safe contact form submissions.
 *
 * Used by pages/contact.php.
 * It expects:
 *   - A valid PDO instance ($db) connected to the pet_insurance DB
 *   - CSRF to be validated by the caller (requireValidCsrf())
 *   - Sanitization helpers from includes/sanitize.php
 */

require_once __DIR__ . '/../includes/sanitize.php';

class ContactSubmit
{
    /**
     * Process a contact form POST and insert into `contact` table.
     *
     * @param  PDO $db
     * @return array ['success' => bool, 'errors' => array]
     */
    public static function handle(PDO $db): array
    {
        $errors = [];

        $name    = inputString('name') ?? '';
        $email   = inputEmail('email') ?? '';
        $phone   = inputString('phone') ?? null;
        $subject = inputString('subject') ?? '';
        $message = inputString('message') ?? '';

        if ($name === '') {
            $errors['name'] = 'Please enter your name.';
        }
        if ($email === '') {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($subject === '') {
            $errors['subject'] = 'Please choose a subject.';
        }
        if ($message === '') {
            $errors['message'] = 'Please enter a message.';
        }

        if ($errors) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        try {
            $stmt = $db->prepare('
                INSERT INTO contact (name, email, phone, subject, message)
                VALUES (:name, :email, :phone, :subject, :message)
            ');
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':phone'   => $phone ?: null,
                ':subject' => $subject,
                ':message' => $message,
            ]);
        } catch (Throwable $e) {
            error_log('ContactSubmit::handle error: ' . $e->getMessage());
            return [
                'success' => false,
                'errors'  => ['general' => 'We could not send your message right now. Please try again later.'],
            ];
        }

        return [
            'success' => true,
            'errors'  => [],
        ];
    }
}
