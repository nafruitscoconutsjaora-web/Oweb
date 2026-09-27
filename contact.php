<?php
declare(strict_types=1);
$pageTitle = 'Support & Inquiries';
require_once __DIR__ . '/includes/header.php';

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid session token. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if (!$name || !$email || !$subject || !$message) {
            $errorMsg = 'Please complete all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = 'Please provide a valid email address.';
        } else {
            try {
                $db = Database::getConnection();
                $ref = generate_ref('TKT');
                $user = current_user();
                $userId = $user ? (int)$user['id'] : null;

                $stmt = $db->prepare('
                    INSERT INTO support_tickets (ticket_ref, user_id, category, subject, priority, status, created_at)
                    VALUES (:ref, :uid, "general", :subj, "medium", "open", NOW())
                ');
                $stmt->execute([
                    'ref'  => $ref,
                    'uid'  => $userId,
                    'subj' => '[' . $email . '] ' . $subject
                ]);
                $ticketId = (int)$db->lastInsertId();

                $msgStmt = $db->prepare('
                    INSERT INTO support_messages (ticket_id, sender_type, sender_id, message, created_at)
                    VALUES (:tid, "user", :sid, :msg, NOW())
                ');
                $msgStmt->execute([
                    'tid' => $ticketId,
                    'sid' => $userId ?: 0,
                    'msg' => "From: $name <$email>\n\n" . $message
                ]);

                $successMsg = 'Your inquiry has been received. Ticket Reference: ' . $ref . '. Our team will respond shortly.';
            } catch (Throwable $e) {
                error_log('[CONTACT ERROR] ' . $e->getMessage());
                $errorMsg = 'Unable to dispatch your message. Please try again later.';
            }
        }
    }
}
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center max-w-2xl mx-auto mb-12">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Customer Support & Inquiries</h1>
        <p class="mt-2 text-sm text-gray-600">
            Have a question or need assistance with your account? Send our team a message below.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Contact Information Cards -->
        <div class="space-y-4">
            <div class="card-premium p-6">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center mb-3">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h4 class="font-bold text-gray-900 text-sm">Direct Support Email</h4>
                <p class="text-xs text-gray-500 mt-1">support@verifex.net</p>
            </div>

            <div class="card-premium p-6">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-[#6D28D9] flex items-center justify-center mb-3">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="font-bold text-gray-900 text-sm">Response Time</h4>
                <p class="text-xs text-gray-500 mt-1">Standard tickets handled within 2-4 business hours.</p>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="md:col-span-2">
            <div class="card-premium p-8">
                <?php if ($successMsg): ?>
                    <div class="p-4 mb-6 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm font-medium">
                        <?= e($successMsg) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errorMsg): ?>
                    <div class="p-4 mb-6 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-medium">
                        <?= e($errorMsg) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <?= csrf_field() ?>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Your Name *</label>
                            <input type="text" name="name" required class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Email Address *</label>
                            <input type="email" name="email" required class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Subject *</label>
                        <input type="text" name="subject" required class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Message *</label>
                        <textarea name="message" rows="5" required class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]"></textarea>
                    </div>

                    <button type="submit" class="btn-primary w-full py-3">
                        Submit Inquiry
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
