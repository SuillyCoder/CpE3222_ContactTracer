<?php
session_start();
require_once 'dbconfig.php';

// Redirect admin away from this page
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin.php');
    exit();
}

// ── Fetch last signed-in user ───────────────────────────────
$last_signin_name = null;
$stmt = $conn->prepare("
    SELECT CONCAT(u.first_name, ' ', u.last_name) AS full_name
    FROM visit_logs vl
    JOIN users u ON vl.user_id = u.user_id
    WHERE vl.status = 'IN'
    ORDER BY vl.time_in DESC
    LIMIT 1
");
$stmt->execute();
$stmt->bind_result($last_signin_name);
$stmt->fetch();
$stmt->close();

// ── Fetch last signed-out user ──────────────────────────────
$last_signout_name = null;
$stmt2 = $conn->prepare("
    SELECT CONCAT(u.first_name, ' ', u.last_name) AS full_name
    FROM visit_logs vl
    JOIN users u ON vl.user_id = u.user_id
    WHERE vl.status = 'OUT'
    ORDER BY vl.time_out DESC
    LIMIT 1
");
$stmt2->execute();
$stmt2->bind_result($last_signout_name);
$stmt2->fetch();
$stmt2->close();

// ── Flash messages ──────────────────────────────────────────
$flash      = $_SESSION['flash']      ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CpE Contact Tracing</title>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=DM+Sans:wght@400;500;600;700&display=swap');

    :root {
        --bg:            #f4f6fa;
        --surface:       #ffffff;
        --surface-2:     #f8fafc;
        --ink:           #0c1a2e;
        --ink-soft:      #3d4d63;
        --ink-muted:     #7a8599;
        --border:        #dde3ec;
        --border-strong: #c3cad6;
        --primary:       #142a52;
        --primary-hover: #0a1a3a;
        --primary-soft:  #e4ecf7;
        --accent:        #d4a017;
        --success:       #2d7a4f;
        --success-soft:  #e3f1e8;
        --danger:        #b54a3a;
        --danger-soft:   #fbe9e5;
        --font-display:  'Fraunces', Georgia, serif;
        --font-body:     'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        --r:             8px;
        --r-lg:          16px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: var(--font-body);
        font-size: 15px;
        line-height: 1.55;
        color: var(--ink);
        background: var(--bg);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        display: grid;
        place-items: center;
        padding: 32px 24px;
        position: relative;
        overflow-x: hidden;
    }
    body::before {
        content: ""; position: absolute;
        top: -200px; right: -200px;
        width: 500px; height: 500px;
        background: radial-gradient(circle, var(--primary-soft) 0%, transparent 70%);
        pointer-events: none;
    }
    body::after {
        content: ""; position: absolute;
        bottom: -250px; left: -250px;
        width: 550px; height: 550px;
        background: radial-gradient(circle, rgba(212,160,23,0.10) 0%, transparent 70%);
        pointer-events: none;
    }

    .container {
        position: relative; z-index: 1;
        width: 100%; max-width: 560px;
    }

    .hero-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 48px 40px 40px;
        box-shadow: 0 12px 32px rgba(12,26,46,.10);
    }

    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 36px; }
    .brand-mark {
        width: 42px; height: 42px;
        background: var(--primary); color: #fff;
        display: grid; place-items: center;
        border-radius: var(--r);
        font-family: var(--font-display);
        font-weight: 600; font-size: 18px;
        letter-spacing: -0.02em;
        position: relative;
    }
    .brand-mark::after {
        content: ""; position: absolute;
        left: 8px; right: 8px; bottom: -4px;
        height: 3px;
        background: var(--accent);
        border-radius: 2px;
    }
    .brand-text {
        font-family: var(--font-display);
        font-weight: 600; font-size: 1.1rem;
        line-height: 1.1; color: var(--ink);
    }
    .brand-text small {
        display: block;
        font-family: var(--font-body);
        font-size: 11px; font-weight: 500;
        letter-spacing: 0.06em; text-transform: uppercase;
        color: var(--ink-muted);
        margin-top: 4px;
    }

    h1 {
        font-family: var(--font-display);
        font-weight: 500;
        font-size: 2.75rem;
        line-height: 1.05;
        letter-spacing: -0.015em;
        margin-bottom: 12px;
        color: var(--ink);
        font-variation-settings: "opsz" 96, "SOFT" 50;
    }
    .lede {
        color: var(--ink-soft);
        font-size: 16px;
        line-height: 1.55;
        margin-bottom: 32px;
        max-width: 48ch;
    }

    .alert {
        padding: 12px 16px;
        border-radius: var(--r);
        border: 1px solid;
        margin-bottom: 24px;
        font-size: 14px;
    }
    .alert-success { background: var(--success-soft); border-color: #b9dac5; color: var(--success); }
    .alert-error   { background: var(--danger-soft);  border-color: #ecc1b9; color: var(--danger); }

    .actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 32px;
    }
    .btn-big {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        gap: 4px;
        padding: 22px 24px;
        border-radius: var(--r);
        border: 1px solid transparent;
        font-family: var(--font-body);
        text-decoration: none;
        cursor: pointer;
        transition: background .15s, transform .05s, box-shadow .15s;
        min-height: 110px;
    }
    .btn-big:active { transform: translateY(1px); }
    .btn-label {
        font-family: var(--font-display);
        font-weight: 500;
        font-size: 1.5rem;
        letter-spacing: -0.01em;
        font-variation-settings: "opsz" 48;
    }
    .btn-hint { font-size: 12px; font-weight: 500; opacity: 0.7; }

    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); box-shadow: 0 4px 12px rgba(20,42,82,.25); }

    .btn-secondary {
        background: var(--surface-2);
        color: var(--ink);
        border-color: var(--border-strong);
    }
    .btn-secondary:hover { background: #e6ebf2; }

    .status-strip {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        padding-top: 24px;
        border-top: 1px solid var(--border);
    }
    .status-item .status-label {
        display: block;
        font-size: 11px; font-weight: 600;
        color: var(--ink-muted);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 6px;
    }
    .status-item .status-value {
        font-family: var(--font-display);
        font-weight: 500;
        font-size: 15px;
        color: var(--ink);
    }
    .status-item .status-value.muted {
        font-style: italic;
        color: var(--ink-muted);
        font-weight: 400;
    }

    .admin-link {
        text-align: center;
        margin-top: 24px;
        font-size: 13px;
    }
    .admin-link a {
        color: var(--ink-muted);
        text-decoration: none;
        border-bottom: 1px solid transparent;
        transition: color .15s, border-color .15s;
    }
    .admin-link a:hover {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    @media (max-width: 520px) {
        .hero-card { padding: 32px 24px; }
        h1 { font-size: 2.25rem; }
        .actions { grid-template-columns: 1fr; }
        .status-strip { grid-template-columns: 1fr; gap: 12px; }
    }
    </style>
</head>
<body>

<div class="container">
    <div class="hero-card">
        <div class="brand">
            <div class="brand-mark">C</div>
            <div class="brand-text">
                Contact Tracing
                <small>Department of Computer Engineering · USC</small>
            </div>
        </div>

        <h1>Welcome.</h1>
        <p class="lede">Please sign in upon entering the office and sign out when you leave. Your visit will be logged for contact tracing purposes.</p>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash_type === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($flash) ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="user.php" class="btn-big btn-primary">
                <span class="btn-label">Sign in</span>
                <span class="btn-hint">Entering the office</span>
            </a>
            <a href="signout.php" class="btn-big btn-secondary">
                <span class="btn-label">Sign out</span>
                <span class="btn-hint">Leaving the office</span>
            </a>
        </div>

        <div class="status-strip">
            <div class="status-item">
                <span class="status-label">Last signed in</span>
                <span class="status-value <?= $last_signin_name ? '' : 'muted' ?>">
                    <?= $last_signin_name ? htmlspecialchars($last_signin_name) : 'None yet' ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Last signed out</span>
                <span class="status-value <?= $last_signout_name ? '' : 'muted' ?>">
                    <?= $last_signout_name ? htmlspecialchars($last_signout_name) : 'None yet' ?>
                </span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
