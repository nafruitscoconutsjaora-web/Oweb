<?php
declare(strict_types=1);
$pageTitle = 'Wallet & Financial Ledger';
require_once dirname(__DIR__) . '/config/config.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$db = Database::getConnection();

// Fetch wallet record
$wallet = Wallet::getOrCreate($db, $userId);

$filterType = trim($_GET['type'] ?? '');
$transactions = [];

try {
    $sql = 'SELECT * FROM wallet_transactions WHERE user_id = :uid';
    $params = ['uid' => $userId];

    if ($filterType !== '') {
        $sql .= ' AND type = :t';
        $params['t'] = $filterType;
    }

    $sql .= ' ORDER BY created_at DESC LIMIT 50';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[WALLET LEDGER ERROR] ' . $e->getMessage());
}

$successMsg = $_GET['success'] ?? '';
$errorMsg = $_GET['error'] ?? '';

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Account Wallet</h1>
            <p class="text-xs text-gray-500 mt-1">Manage your wallet balance and review transaction history.</p>
        </div>

        <button onclick="document.getElementById('deposit-modal').classList.remove('hidden')" class="btn-primary text-xs py-2.5 px-4 shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Funds
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

    <!-- Overview Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <div class="card-premium p-5 bg-gradient-to-br from-white to-purple-50/40 border-purple-100">
            <div class="text-xs font-semibold text-[#6D28D9] uppercase tracking-wider mb-1">Current Balance</div>
            <div class="text-2xl font-extrabold text-gray-900 font-mono">
                <?= format_currency($wallet['balance']) ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Available for activations</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Deposited</div>
            <div class="text-2xl font-extrabold text-green-600 font-mono">
                <?= format_currency($wallet['total_deposited']) ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Added to wallet</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Spent</div>
            <div class="text-2xl font-extrabold text-gray-800 font-mono">
                <?= format_currency($wallet['total_spent']) ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Used for services</div>
        </div>

        <div class="card-premium p-5">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Refunded</div>
            <div class="text-2xl font-extrabold text-[#F59E0B] font-mono">
                <?= format_currency($wallet['total_refunded']) ?>
            </div>
            <div class="mt-2 text-[11px] text-gray-400">Returned to balance</div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card-premium overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-bold text-gray-900 text-sm">Wallet Transactions</h3>
                <p class="text-xs text-gray-400">History of your deposits, debits, and refunds.</p>
            </div>

            <!-- Filter tabs -->
            <div class="flex items-center gap-1.5 text-xs">
                <a href="?type=" class="px-3 py-1.5 rounded-lg <?= $filterType === '' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-gray-100 text-gray-600' ?>">All</a>
                <a href="?type=credit" class="px-3 py-1.5 rounded-lg <?= $filterType === 'credit' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-gray-100 text-gray-600' ?>">Credits</a>
                <a href="?type=debit" class="px-3 py-1.5 rounded-lg <?= $filterType === 'debit' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-gray-100 text-gray-600' ?>">Debits</a>
                <a href="?type=refund" class="px-3 py-1.5 rounded-lg <?= $filterType === 'refund' ? 'bg-[#6D28D9] text-white font-bold' : 'bg-gray-100 text-gray-600' ?>">Refunds</a>
            </div>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="py-16 text-center text-xs text-gray-400">
                No ledger transactions recorded yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-6">Reference</th>
                            <th class="py-3 px-6">Description</th>
                            <th class="py-3 px-6">Type</th>
                            <th class="py-3 px-6 text-right">Amount</th>
                            <th class="py-3 px-6 text-right">Balance After</th>
                            <th class="py-3 px-6">Timestamp (UTC)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($transactions as $tx): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="py-3.5 px-6 font-mono font-bold text-gray-900">
                                    <?= e($tx['transaction_ref']) ?>
                                </td>
                                <td class="py-3.5 px-6 text-gray-800">
                                    <?= e($tx['description']) ?>
                                </td>
                                <td class="py-3.5 px-6">
                                    <span class="capitalize font-semibold text-[10px] <?= match($tx['type']) {
                                        'credit', 'payment' => 'badge-success',
                                        'refund' => 'badge-warning',
                                        default  => 'badge-purple'
                                    } ?>">
                                        <?= e($tx['type']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right font-mono font-bold <?= $tx['type'] === 'debit' ? 'text-gray-900' : 'text-green-600' ?>">
                                    <?= $tx['type'] === 'debit' ? '-' : '+' ?><?= format_currency($tx['amount']) ?>
                                </td>
                                <td class="py-3.5 px-6 text-right font-mono text-gray-600">
                                    <?= format_currency($tx['balance_after']) ?>
                                </td>
                                <td class="py-3.5 px-6 text-gray-400">
                                    <?= date('Y-m-d H:i:s', strtotime($tx['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Deposit Modal -->
<div id="deposit-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="card-premium p-6 max-w-md w-full bg-white relative">
        <button onclick="document.getElementById('deposit-modal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        
        <h3 class="text-lg font-bold text-gray-900 mb-1">Add Prepaid Funds</h3>
        <p class="text-xs text-gray-500 mb-5">Select amount to top up your account balance.</p>

        <form action="/user/payment-create.php" method="POST" class="space-y-4">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">Deposit Amount (USD)</label>
                <div class="relative">
                    <span class="absolute left-3.5 top-2.5 text-gray-400 text-sm font-bold">$</span>
                    <input type="number" step="0.01" min="5" max="1000" name="amount" value="10.00" required
                        class="w-full pl-8 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-hidden focus:border-[#6D28D9] font-mono">
                </div>
                <div class="text-[11px] text-gray-400 mt-1">Minimum: $5.00 • Maximum: $1,000.00</div>
            </div>

            <div class="grid grid-cols-4 gap-2 pt-1">
                <button type="button" onclick="document.querySelector('input[name=amount]').value='10.00'" class="btn-secondary text-xs py-1.5">$10</button>
                <button type="button" onclick="document.querySelector('input[name=amount]').value='25.00'" class="btn-secondary text-xs py-1.5">$25</button>
                <button type="button" onclick="document.querySelector('input[name=amount]').value='50.00'" class="btn-secondary text-xs py-1.5">$50</button>
                <button type="button" onclick="document.querySelector('input[name=amount]').value='100.00'" class="btn-secondary text-xs py-1.5">$100</button>
            </div>

            <button type="submit" class="btn-primary w-full py-2.5 text-xs font-semibold mt-4">
                Proceed to Payment Gateway &rarr;
            </button>
        </form>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
