<?php
declare(strict_types=1);
$pageTitle = 'Support Tickets';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

$activeTicketId = (int)($_GET['ticket'] ?? 0);
$successMsg = '';
$errorMsg = '';

// Handle New Ticket Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Security validation failed.';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
        $message = trim($_POST['message'] ?? '');

        if (!$subject || !$message) {
            $errorMsg = 'Please provide both subject and message details.';
        } else {
            try {
                $ref = generate_ref('TKT');
                $stmt = $db->prepare('
                    INSERT INTO support_tickets (ticket_ref, user_id, order_id, category, subject, priority, status, created_at)
                    VALUES (:ref, :uid, :oid, :cat, :subj, "medium", "open", NOW())
                ');
                $stmt->execute([
                    'ref'  => $ref,
                    'uid'  => $userId,
                    'oid'  => $orderId,
                    'cat'  => $category,
                    'subj' => $subject
                ]);
                $tId = (int)$db->lastInsertId();

                $msgStmt = $db->prepare('
                    INSERT INTO support_messages (ticket_id, sender_type, sender_id, message, created_at)
                    VALUES (:tid, "user", :sid, :msg, NOW())
                ');
                $msgStmt->execute(['tid' => $tId, 'sid' => $userId, 'msg' => $message]);

                $successMsg = 'Ticket #' . $ref . ' has been opened. Our support staff will respond shortly.';
            } catch (Throwable $e) {
                error_log('[TICKET CREATE ERROR] ' . $e->getMessage());
                $errorMsg = 'Failed to submit support ticket.';
            }
        }
    }
}

// Handle Ticket Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Security validation failed.';
    } else {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (!$ticketId || !$message) {
            $errorMsg = 'Message cannot be empty.';
        } else {
            try {
                // Verify ownership
                $chk = $db->prepare('SELECT id FROM support_tickets WHERE id = :id AND user_id = :uid LIMIT 1');
                $chk->execute(['id' => $ticketId, 'uid' => $userId]);
                if ($chk->fetch()) {
                    $ins = $db->prepare('
                        INSERT INTO support_messages (ticket_id, sender_type, sender_id, message, created_at)
                        VALUES (:tid, "user", :sid, :msg, NOW())
                    ');
                    $ins->execute(['tid' => $ticketId, 'sid' => $userId, 'msg' => $message]);

                    $upd = $db->prepare('UPDATE support_tickets SET status = "open", updated_at = NOW() WHERE id = :id');
                    $upd->execute(['id' => $ticketId]);

                    $successMsg = 'Reply sent successfully.';
                    $activeTicketId = $ticketId;
                }
            } catch (Throwable $e) {
                error_log('[TICKET REPLY ERROR] ' . $e->getMessage());
                $errorMsg = 'Failed to send reply.';
            }
        }
    }
}

// Fetch user's tickets
$tickets = [];
$activeTicket = null;
$ticketMessages = [];

try {
    $tStmt = $db->prepare('
        SELECT t.*, o.order_number
        FROM support_tickets t
        LEFT JOIN orders o ON o.id = t.order_id
        WHERE t.user_id = :uid
        ORDER BY t.updated_at DESC
    ');
    $tStmt->execute(['uid' => $userId]);
    $tickets = $tStmt->fetchAll();

    if ($activeTicketId > 0) {
        $singleStmt = $db->prepare('SELECT * FROM support_tickets WHERE id = :id AND user_id = :uid LIMIT 1');
        $singleStmt->execute(['id' => $activeTicketId, 'uid' => $userId]);
        $activeTicket = $singleStmt->fetch();

        if ($activeTicket) {
            $mStmt = $db->prepare('
                SELECT sm.*, u.name AS sender_name
                FROM support_messages sm
                LEFT JOIN users u ON u.id = sm.sender_id
                WHERE sm.ticket_id = :tid
                ORDER BY sm.created_at ASC
            ');
            $mStmt->execute(['tid' => $activeTicketId]);
            $ticketMessages = $mStmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    error_log('[SUPPORT FETCH ERROR] ' . $e->getMessage());
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Support Helpdesk</h1>
            <p class="text-xs text-gray-500 mt-1">Get assistance with order delivery, API setup, or billing inquiries.</p>
        </div>

        <button onclick="document.getElementById('new-ticket-modal').classList.remove('hidden')" class="btn-primary text-xs py-2.5 px-4 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Create New Ticket
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="card-premium p-4 mb-6 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="card-premium p-4 mb-6 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($errorMsg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Ticket List (Left) -->
        <div class="lg:col-span-5">
            <div class="card-premium p-5">
                <h3 class="font-bold text-gray-900 text-sm mb-4">Your Support Tickets</h3>

                <?php if (empty($tickets)): ?>
                    <div class="py-12 text-center text-xs text-gray-400">
                        No support tickets opened yet.
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($tickets as $t): ?>
                            <a href="?ticket=<?= (int)$t['id'] ?>" class="block p-3.5 rounded-xl border transition-colors <?= $activeTicketId === (int)$t['id'] ? 'bg-purple-50/70 border-purple-200' : 'border-gray-100 hover:bg-gray-50' ?>">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-mono font-bold text-gray-800">#<?= e($t['ticket_ref']) ?></span>
                                    <span class="capitalize text-[10px] font-semibold <?= match($t['status']) {
                                        'resolved', 'closed' => 'badge-success',
                                        'open' => 'badge-purple',
                                        default => 'badge-warning'
                                    } ?>">
                                        <?= e($t['status']) ?>
                                    </span>
                                </div>
                                <div class="font-semibold text-gray-900 text-xs truncate"><?= e($t['subject']) ?></div>
                                <div class="text-[10px] text-gray-400 mt-1"><?= date('M j, Y H:i', strtotime($t['created_at'])) ?> UTC</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ticket Thread (Right) -->
        <div class="lg:col-span-7">
            <?php if ($activeTicket): ?>
                <div class="card-premium p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                        <div>
                            <span class="text-xs text-gray-400 font-mono">Ticket #<?= e($activeTicket['ticket_ref']) ?></span>
                            <h2 class="text-lg font-bold text-gray-900"><?= e($activeTicket['subject']) ?></h2>
                        </div>
                        <span class="badge-purple font-mono text-xs"><?= e($activeTicket['category']) ?></span>
                    </div>

                    <!-- Messages Stream -->
                    <div class="space-y-4 mb-6 max-h-[450px] overflow-y-auto pr-2">
                        <?php foreach ($ticketMessages as $m): ?>
                            <div class="p-4 rounded-xl <?= $m['sender_type'] === 'admin' ? 'bg-purple-50/80 border border-purple-100 ml-4' : 'bg-gray-50 border border-gray-100 mr-4' ?> text-xs">
                                <div class="flex justify-between items-center text-gray-400 text-[10px] mb-1.5">
                                    <span class="font-bold <?= $m['sender_type'] === 'admin' ? 'text-[#6D28D9]' : 'text-gray-700' ?>">
                                        <?= $m['sender_type'] === 'admin' ? 'Support Specialist' : e($m['sender_name'] ?: 'You') ?>
                                    </span>
                                    <span><?= date('M j, Y H:i', strtotime($m['created_at'])) ?> UTC</span>
                                </div>
                                <div class="text-gray-800 whitespace-pre-wrap leading-relaxed"><?= e($m['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Reply Input -->
                    <?php if ($activeTicket['status'] !== 'closed'): ?>
                        <form method="POST" class="space-y-3 pt-4 border-t border-gray-100">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reply_ticket">
                            <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">

                            <textarea name="message" rows="3" required placeholder="Type your reply to the support team..."
                                class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]"></textarea>

                            <button type="submit" class="btn-primary text-xs py-2 px-4">
                                Send Reply
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card-premium p-12 text-center text-xs text-gray-400">
                    Select a ticket from the left panel to view correspondence, or open a new ticket.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Ticket Modal -->
<div id="new-ticket-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-lg w-full bg-white relative">
        <button onclick="document.getElementById('new-ticket-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        
        <h3 class="text-lg font-bold text-gray-900 mb-1">Create Support Ticket</h3>
        <p class="text-xs text-gray-500 mb-5">Describe your issue in detail so our operations team can resolve it.</p>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_ticket">

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Category</label>
                <select name="category" class="w-full px-3.5 py-2 text-xs border border-gray-200 rounded-xl bg-white focus:outline-hidden focus:border-[#6D28D9]">
                    <option value="activation">Activation / SMS Delivery</option>
                    <option value="billing">Prepaid Wallet / Billing</option>
                    <option value="api">B2B Developer API</option>
                    <option value="general">General Inquiries</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Subject</label>
                <input type="text" name="subject" required placeholder="Brief summary of the issue"
                    class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Detailed Message</label>
                <textarea name="message" rows="4" required placeholder="Include order numbers or error details if relevant..."
                    class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]"></textarea>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-2">
                Submit Support Ticket
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
