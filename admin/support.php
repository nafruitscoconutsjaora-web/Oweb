<?php
declare(strict_types=1);
$adminTitle = 'Customer Support Desk';
require_once dirname(__DIR__) . '/includes/admin_header.php';
require_admin('support.manage');

$db = Database::getConnection();
$admin = current_user();
$adminId = (int)$admin['id'];

$activeTicketId = (int)($_GET['ticket'] ?? 0);
$message = '';
$error = '';

// Handle Admin Reply / Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired.';
    } else {
        $action = $_POST['action'];

        if ($action === 'reply_ticket') {
            $ticketId = (int)($_POST['ticket_id'] ?? 0);
            $reply = trim($_POST['message'] ?? '');
            $newStatus = trim($_POST['status'] ?? 'pending');

            if (!$ticketId || !$reply) {
                $error = 'Reply message cannot be empty.';
            } else {
                try {
                    $ins = $db->prepare('
                        INSERT INTO support_messages (ticket_id, sender_type, sender_id, message, created_at)
                        VALUES (:tid, "admin", :aid, :msg, NOW())
                    ');
                    $ins->execute(['tid' => $ticketId, 'aid' => $adminId, 'msg' => $reply]);

                    $upd = $db->prepare('UPDATE support_tickets SET status = :st, updated_at = NOW() WHERE id = :id');
                    $upd->execute(['st' => $newStatus, 'id' => $ticketId]);

                    // Notify customer
                    $tStmt = $db->prepare('SELECT user_id, ticket_ref FROM support_tickets WHERE id = :id LIMIT 1');
                    $tStmt->execute(['id' => $ticketId]);
                    $ticketInfo = $tStmt->fetch();
                    if ($ticketInfo && !empty($ticketInfo['user_id'])) {
                        Notifications::sendUser(
                            (int)$ticketInfo['user_id'],
                            'support',
                            'Support Agent Replied',
                            'New response received on Ticket #' . $ticketInfo['ticket_ref'],
                            '/user/support.php?ticket=' . $ticketId
                        );
                    }

                    $message = 'Reply posted and ticket updated to ' . $newStatus . '.';
                    $activeTicketId = $ticketId;
                } catch (Throwable $e) {
                    error_log('[ADMIN SUPPORT ERROR] ' . $e->getMessage());
                    $error = 'Failed to post reply.';
                }
            }
        }
    }
}

// Fetch all tickets
$tickets = [];
$activeTicket = null;
$messages = [];

try {
    $tStmt = $db->query('
        SELECT t.*, u.name AS user_name, u.email AS user_email
        FROM support_tickets t
        LEFT JOIN users u ON u.id = t.user_id
        ORDER BY t.updated_at DESC
        LIMIT 50
    ');
    $tickets = $tStmt->fetchAll();

    if ($activeTicketId > 0) {
        $single = $db->prepare('
            SELECT t.*, u.name AS user_name, u.email AS user_email
            FROM support_tickets t
            LEFT JOIN users u ON u.id = t.user_id
            WHERE t.id = :id
            LIMIT 1
        ');
        $single->execute(['id' => $activeTicketId]);
        $activeTicket = $single->fetch();

        if ($activeTicket) {
            $mStmt = $db->prepare('
                SELECT sm.*, u.name AS admin_name
                FROM support_messages sm
                LEFT JOIN users u ON u.id = sm.sender_id AND sm.sender_type = "admin"
                WHERE sm.ticket_id = :tid
                ORDER BY sm.created_at ASC
            ');
            $mStmt->execute(['tid' => $activeTicketId]);
            $messages = $mStmt->fetchAll();
        }
    }
} catch (Throwable $e) {
    error_log('[ADMIN SUPPORT FETCH ERROR] ' . $e->getMessage());
}
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-gray-900">Support Helpdesk Queue</h2>
            <p class="text-xs text-gray-500 mt-0.5">Manage customer inquiries, order delivery investigations, and technical issues.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="card-premium p-4 bg-green-50 border-green-200 text-green-800 text-xs font-semibold">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="card-premium p-4 bg-red-50 border-red-200 text-red-700 text-xs font-semibold">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Ticket Queue (Left) -->
        <div class="lg:col-span-5">
            <div class="card-premium p-5">
                <h3 class="font-bold text-gray-900 text-sm mb-3">Ticket Inbox (<?= count($tickets) ?>)</h3>

                <?php if (empty($tickets)): ?>
                    <div class="py-12 text-center text-xs text-gray-400">
                        No support tickets currently pending.
                    </div>
                <?php else: ?>
                    <div class="space-y-2 max-h-[600px] overflow-y-auto pr-1">
                        <?php foreach ($tickets as $t): ?>
                            <a href="?ticket=<?= (int)$t['id'] ?>" class="block p-3 rounded-xl border transition-colors <?= $activeTicketId === (int)$t['id'] ? 'bg-purple-50/70 border-purple-300' : 'border-gray-100 hover:bg-gray-50' ?>">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-mono font-bold text-gray-800">#<?= e($t['ticket_ref']) ?></span>
                                    <span class="capitalize text-[10px] font-semibold <?= match($t['status']) {
                                        'open' => 'badge-danger',
                                        'resolved', 'closed' => 'badge-success',
                                        default => 'badge-warning'
                                    } ?>">
                                        <?= e($t['status']) ?>
                                    </span>
                                </div>
                                <div class="font-bold text-gray-900 text-xs truncate"><?= e($t['subject']) ?></div>
                                <div class="text-[11px] text-gray-500 mt-1 flex justify-between">
                                    <span><?= e($t['user_name'] ?: 'Guest') ?></span>
                                    <span class="text-gray-400"><?= date('M j, H:i', strtotime($t['created_at'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ticket Thread & Reply (Right) -->
        <div class="lg:col-span-7">
            <?php if ($activeTicket): ?>
                <div class="card-premium p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                        <div>
                            <span class="text-xs text-gray-400 font-mono">Ticket #<?= e($activeTicket['ticket_ref']) ?></span>
                            <h2 class="text-lg font-bold text-gray-900"><?= e($activeTicket['subject']) ?></h2>
                            <div class="text-xs text-gray-500 mt-0.5">User: <?= e($activeTicket['user_name'] ?: 'Guest') ?> &lt;<?= e($activeTicket['user_email'] ?: 'N/A') ?>&gt;</div>
                        </div>
                        <span class="badge-purple font-mono text-xs capitalize"><?= e($activeTicket['category']) ?></span>
                    </div>

                    <!-- Messages -->
                    <div class="space-y-4 mb-6 max-h-[420px] overflow-y-auto pr-2">
                        <?php foreach ($messages as $m): ?>
                            <div class="p-4 rounded-xl <?= $m['sender_type'] === 'admin' ? 'bg-purple-50/80 border border-purple-100 mr-6' : 'bg-gray-50 border border-gray-100 ml-6' ?> text-xs">
                                <div class="flex justify-between items-center text-gray-400 text-[10px] mb-1.5">
                                    <span class="font-bold <?= $m['sender_type'] === 'admin' ? 'text-[#6D28D9]' : 'text-gray-800' ?>">
                                        <?= $m['sender_type'] === 'admin' ? ('Staff: ' . e($m['admin_name'] ?: 'Admin')) : 'Customer' ?>
                                    </span>
                                    <span><?= date('Y-m-d H:i:s', strtotime($m['created_at'])) ?> UTC</span>
                                </div>
                                <div class="text-gray-800 whitespace-pre-wrap leading-relaxed"><?= e($m['message']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Administrative Reply Form -->
                    <form method="POST" class="space-y-3 pt-4 border-t border-gray-100">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reply_ticket">
                        <input type="hidden" name="ticket_id" value="<?= (int)$activeTicket['id'] ?>">

                        <textarea name="message" rows="3" required placeholder="Type official response to customer..."
                            class="w-full px-3.5 py-2.5 text-xs border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9]"></textarea>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <label class="text-xs text-gray-600 font-semibold">Update Status:</label>
                                <select name="status" class="px-2.5 py-1 text-xs border border-gray-200 rounded-lg bg-white">
                                    <option value="pending" <?= $activeTicket['status'] === 'pending' ? 'selected' : '' ?>>Pending Customer</option>
                                    <option value="resolved" <?= $activeTicket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="closed" <?= $activeTicket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-primary text-xs py-2 px-4">
                                Post Reply & Update
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-premium p-12 text-center text-xs text-gray-400">
                    Select a ticket from the left panel to review message correspondence.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
