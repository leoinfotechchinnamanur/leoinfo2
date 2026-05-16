<?php
// user/donations.php — Send and view donation cards
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
$pageTitle = 'Donation Cards';

// Get available cards
$cards = [];
try {
    $cards = $pdo->query("SELECT * FROM donation_cards WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {}

// Get donation history
$history = [];
try {
    $stmt = $pdo->prepare("
        SELECT gt.*, dc.name as card_name, dc.image_url, s.name as sender_name, r.name as receiver_name
        FROM gift_transactions gt
        JOIN donation_cards dc ON gt.gift_id = dc.card_id
        JOIN users s ON gt.sender_id = s.user_id
        JOIN users r ON gt.receiver_id = r.user_id
        WHERE gt.sender_id = ? OR gt.receiver_id = ?
        ORDER BY gt.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['user_id'], $user['user_id']]);
    $history = $stmt->fetchAll();
} catch (Exception $e) {}

// Get users for dropdown
$users = [];
try {
    // FIX Priority 6: Use prepared statement to prevent SQL injection
    $stmt = $pdo->prepare("SELECT user_id, name, email FROM users WHERE user_id != ? ORDER BY name");
    $stmt->execute([$user['user_id']]);
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

$csrf_token = generateCSRFToken();
include __DIR__ . '/../includes/header.php';
?>

<style>
:root {
    --bg: #08080c; --card: #0f0f14; --border: #1a1a22;
    --text: #a1a1aa; --bright: #ffffff; --accent: #6366f1;
    --green: #10b981; --red: #ef4444; --yellow: #f59e0b;
}
.don-wrap { max-width: 900px; margin: 0 auto; padding: 16px; }
.don-header { margin-bottom: 20px; }
.don-header h1 { font-size: 24px; color: var(--bright); font-weight: 800; }

.card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.don-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.don-card:hover, .don-card.selected {
    border-color: var(--accent);
    background: #121218;
}
.don-card .emoji { font-size: 40px; display: block; margin-bottom: 10px; }
.don-card h3 { font-size: 16px; color: var(--bright); margin-bottom: 6px; }
.don-card p { font-size: 12px; color: var(--text); margin-bottom: 8px; }
.don-card .range {
    font-size: 14px;
    color: var(--green);
    font-weight: 700;
}

.send-form {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    display: none;
}
.send-form.open { display: block; }

.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-size: 13px;
    color: var(--text);
    margin-bottom: 6px;
    font-weight: 600;
}
.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: #1a1a22;
    color: var(--bright);
    font-size: 14px;
}
.form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: var(--accent);
}
.submit-btn {
    width: 100%;
    padding: 14px;
    border-radius: 12px;
    border: none;
    background: var(--accent);
    color: white;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.history-list { display: flex; flex-direction: column; gap: 10px; }
.history-item {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.history-item .emoji { font-size: 28px; }
.history-details { flex: 1; }
.history-title { font-size: 14px; color: var(--bright); font-weight: 700; }
.history-meta { font-size: 12px; color: var(--text); }
.history-amount {
    font-size: 16px;
    font-weight: 800;
}
.sent { color: var(--red); }
.received { color: var(--green); }

@media (max-width: 640px) {
    .card-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .don-card { padding: 14px; }
}
</style>

<div class="don-wrap">
    <div class="don-header">
        <h1><span class="emoji">💳</span> Donation Cards</h1>
    </div>

    <div class="card-grid" id="cardGrid">
        <?php foreach ($cards as $c): ?>
        <div class="don-card" data-card-id="<?= $c['card_id'] ?>" data-min="<?= $c['min_amount'] ?>" data-max="<?= $c['max_amount'] ?>" onclick="selectCard(this)">
            <span class="emoji">💳</span>
            <h3><?= htmlspecialchars($c['name']) ?></h3>
            <p><?= htmlspecialchars($c['message_template'] ?? '') ?></p>
            <div class="range">🪙 <?= number_format($c['min_amount'], 0) ?> - <?= $c['max_amount'] > 0 ? number_format($c['max_amount'], 0) : '∞' ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($cards)): ?>
            <p style="color: var(--text); grid-column: 1/-1; text-align: center;">No donation cards available yet.</p>
        <?php endif; ?>
    </div>

    <div class="send-form" id="sendForm">
        <h3 style="color: var(--bright); margin-bottom: 16px;">Send Donation Card</h3>
        <form id="donationForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="card_id" id="selectedCardId">

            <div class="form-group">
                <label>Select Recipient</label>
                <select name="receiver_id" required>
                    <option value="">Choose user...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Amount (coins)</label>
                <input type="number" name="amount" id="amountInput" step="0.01" min="1" required placeholder="Enter amount">
            </div>

            <div class="form-group">
                <label>Personal Message</label>
                <textarea name="message" rows="2" placeholder="Optional message..."></textarea>
            </div>

            <button type="submit" class="submit-btn">💳 Send Donation</button>
        </form>
    </div>

    <h2 style="font-size: 18px; color: var(--bright); margin-bottom: 14px;">History</h2>
    <div class="history-list">
        <?php foreach ($history as $h): 
            $isSender = $h['sender_id'] === $user['user_id'];
        ?>
        <div class="history-item">
            <span class="emoji">💳</span>
            <div class="history-details">
                <div class="history-title"><?= htmlspecialchars($h['card_name']) ?></div>
                <div class="history-meta">
                    <?= $isSender ? 'To' : 'From' ?> <?= htmlspecialchars($isSender ? $h['receiver_name'] : $h['sender_name']) ?>
                    • <?= date('M d, H:i', strtotime($h['created_at'])) ?>
                    <?php if ($h['message']): ?>• "<?= htmlspecialchars($h['message']) ?>"<?php endif; ?>
                </div>
            </div>
            <div class="history-amount <?= $isSender ? 'sent' : 'received' ?>">
                <?= $isSender ? '-' : '+' ?>🪙 <?= number_format($h['coin_amount'], 0) ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
            <p style="color: var(--text); text-align: center; padding: 40px;">No donations yet. Send one above!</p>
        <?php endif; ?>
    </div>
</div>

<script>
let selectedCard = null;

function selectCard(el) {
    document.querySelectorAll('.don-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedCard = el;

    document.getElementById('selectedCardId').value = el.dataset.cardId;
    const min = parseFloat(el.dataset.min);
    const max = parseFloat(el.dataset.max);
    const input = document.getElementById('amountInput');
    input.min = min;
    input.placeholder = `Min: ${min}${max > 0 ? ', Max: ' + max : ''}`;
    document.getElementById('sendForm').classList.add('open');
}

document.getElementById('donationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('.submit-btn');
    btn.disabled = true;
    btn.textContent = 'Sending...';

    try {
        const formData = new FormData(this);
        const res = await fetch('/api/send-donation.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.error || 'Failed to send');
            btn.disabled = false;
            btn.textContent = '💳 Send Donation';
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled = false;
        btn.textContent = '💳 Send Donation';
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>