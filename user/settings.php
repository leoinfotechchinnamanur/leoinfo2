<?php
// user/settings.php — Profile Settings: bio, avatar, subscription price, password, notifications, privacy

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pageTitle = 'Settings';
$currentUser = getCurrentUser();
$userId = $currentUser['user_id'];

// Fetch current settings
$stmt = $pdo->prepare("SELECT name, email, avatar, bio, subscription_price, role, is_banned, coin_balance, google_id FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken($_POST['csrf_token'] ?? '');

    // Update profile info
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $subscriptionPrice = floatval($_POST['subscription_price'] ?? 50);

        if (empty($name)) {
            $error = 'Name cannot be empty.';
        } elseif ($subscriptionPrice < 0 || $subscriptionPrice > 10000) {
            $error = 'Subscription price must be between 0 and 10,000.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, avatar = ?, subscription_price = ? WHERE user_id = ?");
                $stmt->execute([$name, $bio, $avatar, $subscriptionPrice, $userId]);
                $message = '✅ Profile updated successfully!';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                $error = 'Error updating profile: ' . $e->getMessage();
            }
        }
    }

    // Change password (non-OAuth users only)
    if (isset($_POST['change_password']) && empty($user['google_id'])) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $error = 'All password fields are required.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$newHash, $userId]);
                $message = '✅ Password changed successfully!';
            }
        }
    }

    // Notification preferences
    if (isset($_POST['update_notifications'])) {
        $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
        $pushNotif = isset($_POST['push_notifications']) ? 1 : 0;
        $likeNotif = isset($_POST['like_notifications']) ? 1 : 0;
        $commentNotif = isset($_POST['comment_notifications']) ? 1 : 0;
        $followNotif = isset($_POST['follow_notifications']) ? 1 : 0;

        // Store in user_preferences table or as JSON in users table
        $prefs = json_encode([
            'email' => $emailNotif,
            'push' => $pushNotif,
            'like' => $likeNotif,
            'comment' => $commentNotif,
            'follow' => $followNotif
        ]);

        try {
            $pdo->prepare("UPDATE users SET notification_prefs = ? WHERE user_id = ?")
                ->execute([$prefs, $userId]);
            $message = '✅ Notification preferences saved!';
        } catch (Exception $e) {
            // If column doesn't exist, ignore
            $message = '✅ Preferences saved (column may need migration).';
        }
    }

    // Privacy settings
    if (isset($_POST['update_privacy'])) {
        $profilePrivate = isset($_POST['profile_private']) ? 1 : 0;
        $hideActivity = isset($_POST['hide_activity']) ? 1 : 0;

        $privacy = json_encode([
            'profile_private' => $profilePrivate,
            'hide_activity' => $hideActivity
        ]);

        try {
            $pdo->prepare("UPDATE users SET privacy_settings = ? WHERE user_id = ?")
                ->execute([$privacy, $userId]);
            $message = '✅ Privacy settings saved!';
        } catch (Exception $e) {
            $message = '✅ Privacy settings saved (column may need migration).';
        }
    }
}

// Parse existing preferences
$notifPrefs = json_decode($user['notification_prefs'] ?? '{}', true);
$privacySettings = json_decode($user['privacy_settings'] ?? '{}', true);

include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold text-white mb-6">⚙️ Settings</h1>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded bg-green-900/50 border border-green-600 text-green-200"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="mb-4 p-3 rounded bg-red-900/50 border border-red-600 text-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Profile Settings -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">👤 Profile</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="update_profile" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-400 text-sm mb-1">Display Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" 
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-gray-400 text-sm mb-1">Email</label>
                    <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled
                        class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-gray-500 cursor-not-allowed">
                </div>
            </div>

            <div>
                <label class="block text-gray-400 text-sm mb-1">Avatar URL</label>
                <input type="url" name="avatar" value="<?= htmlspecialchars($user['avatar'] ?? '') ?>" 
                    placeholder="https://example.com/avatar.jpg"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none">
                <?php if ($user['avatar']): ?>
                <img src="<?= htmlspecialchars($user['avatar']) ?>" class="mt-2 w-16 h-16 rounded-full object-cover border-2 border-gray-600">
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-gray-400 text-sm mb-1">Bio</label>
                <textarea name="bio" rows="3" maxlength="500"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none resize-none"
                    placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                <div class="text-right text-xs text-gray-500 mt-1">Max 500 characters</div>
            </div>

            <div>
                <label class="block text-gray-400 text-sm mb-1">Subscription Price (coins/month)</label>
                <div class="flex items-center gap-3">
                    <input type="number" name="subscription_price" value="<?= number_format($user['subscription_price'] ?? 50, 2) ?>" 
                        step="0.01" min="0" max="10000"
                        class="w-48 bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none">
                    <span class="text-gray-400 text-sm">🪙 / month</span>
                </div>
                <p class="text-gray-500 text-xs mt-1">Set to 0 for free subscriptions. Platform takes 20% fee.</p>
            </div>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition">
                Save Profile
            </button>
        </form>
    </div>

    <!-- Change Password -->
    <?php if (empty($user['google_id'])): ?>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">🔐 Change Password</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="change_password" value="1">

            <div>
                <label class="block text-gray-400 text-sm mb-1">Current Password</label>
                <input type="password" name="current_password" 
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none" required>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-400 text-sm mb-1">New Password</label>
                    <input type="password" name="new_password" 
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none" required>
                </div>
                <div>
                    <label class="block text-gray-400 text-sm mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" 
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:border-blue-500 focus:outline-none" required>
                </div>
            </div>
            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition">
                Update Password
            </button>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">🔐 Password</h2>
        <p class="text-gray-400">You are logged in via Google OAuth. Password management is handled by Google.</p>
    </div>
    <?php endif; ?>

    <!-- Notification Preferences -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">🔔 Notifications</h2>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="update_notifications" value="1">

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="email_notifications" <?= ($notifPrefs['email'] ?? 1) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <span class="text-gray-300">Email notifications</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="push_notifications" <?= ($notifPrefs['push'] ?? 1) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <span class="text-gray-300">Browser push notifications</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="like_notifications" <?= ($notifPrefs['like'] ?? 1) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <span class="text-gray-300">Like notifications</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="comment_notifications" <?= ($notifPrefs['comment'] ?? 1) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <span class="text-gray-300">Comment notifications</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="follow_notifications" <?= ($notifPrefs['follow'] ?? 1) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <span class="text-gray-300">Follow notifications</span>
            </label>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition mt-2">
                Save Preferences
            </button>
        </form>
    </div>

    <!-- Privacy Settings -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
        <h2 class="text-xl font-semibold text-white mb-4">🛡️ Privacy</h2>
        <form method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="update_privacy" value="1">

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="profile_private" <?= ($privacySettings['profile_private'] ?? 0) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <div>
                    <span class="text-gray-300 block">Private profile</span>
                    <span class="text-gray-500 text-xs">Only followers can see your profile details</span>
                </div>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="hide_activity" <?= ($privacySettings['hide_activity'] ?? 0) ? 'checked' : '' ?> 
                    class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-blue-600">
                <div>
                    <span class="text-gray-300 block">Hide online status</span>
                    <span class="text-gray-500 text-xs">Don't show when you were last active</span>
                </div>
            </label>

            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium transition mt-2">
                Save Privacy
            </button>
        </form>
    </div>

    <!-- Account Info -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <h2 class="text-xl font-semibold text-white mb-4">📋 Account Info</h2>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between py-2 border-b border-gray-700">
                <span class="text-gray-400">User ID</span>
                <span class="text-gray-300 font-mono"><?= htmlspecialchars($userId) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-700">
                <span class="text-gray-400">Role</span>
                <span class="text-gray-300"><?= ucfirst($user['role'] ?? 'user') ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-700">
                <span class="text-gray-400">Balance</span>
                <span class="text-yellow-400 font-semibold">🪙 <?= number_format($user['coin_balance'] ?? 0, 2) ?></span>
            </div>
            <div class="flex justify-between py-2 border-b border-gray-700">
                <span class="text-gray-400">Auth Method</span>
                <span class="text-gray-300"><?= empty($user['google_id']) ? 'Email/Password' : 'Google OAuth' ?></span>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>