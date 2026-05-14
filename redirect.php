<?php
require_once 'includes/functions.php';

$url = $_GET['url'] ?? '';
if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    header("Location: /");
    exit;
}

// Validate URL scheme
$parsed = parse_url($url);
if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
    header("Location: /");
    exit;
}

$pageTitle = 'Leaving AkkuApps';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting... | AkkuApps</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .neu-card {
            background: linear-gradient(145deg, #ffffff, #f3f4f6);
            border-radius: 1rem;
            box-shadow: 8px 8px 16px #d1d5db, -8px -8px 16px #ffffff;
        }
        html.dark .neu-card {
            background: linear-gradient(145deg, #1e293b, #0f172a);
            box-shadow: 8px 8px 16px #0a0f1a, -8px -8px 16px #28354a;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-slate-900 text-gray-800 dark:text-gray-200 min-h-screen flex items-center justify-center p-4">

<div class="max-w-md w-full neu-card p-8 text-center">
    <div class="text-6xl mb-4">🚪</div>
    <h1 class="text-2xl font-bold mb-2">You're Leaving AkkuApps</h1>
    <p class="text-gray-600 dark:text-gray-400 mb-6 text-sm">
        You are being redirected to an external website. We are not responsible for the content of external sites.
    </p>
    
    <div class="neu-card p-4 mb-6 bg-yellow-50 dark:bg-yellow-900/20 break-all text-sm font-mono text-amber-700 dark:text-amber-400">
        <?php echo htmlspecialchars($url); ?>
    </div>
    
    <div class="space-y-3">
        <a href="<?php echo htmlspecialchars($url); ?>" 
           class="block w-full neu-button py-3 rounded-full font-bold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30">
            Continue (5)
        </a>
        <a href="javascript:history.back()" 
           class="block w-full neu-button py-3 rounded-full text-gray-600">
            Go Back
        </a>
    </div>
    
    <p class="mt-4 text-xs text-gray-500">
        Redirecting automatically in <span id="countdown">5</span> seconds...
    </p>
</div>

<script>
let seconds = 5;
const countdownEl = document.getElementById('countdown');
const continueBtn = document.querySelector('a[href="<?php echo htmlspecialchars($url); ?>"]');

const timer = setInterval(() => {
    seconds--;
    countdownEl.textContent = seconds;
    continueBtn.textContent = `Continue (${seconds})`;
    
    if (seconds <= 0) {
        clearInterval(timer);
        window.location.href = '<?php echo htmlspecialchars($url); ?>';
    }
}, 1000);
</script>

</body>
</html>
