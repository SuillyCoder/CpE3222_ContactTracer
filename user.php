<?php
session_start();
require_once 'dbconfig.php';

$error = '';
$step  = 'id';          // 'id' → ask for ID first; 'confirm' → show retrieved info
$user  = null;

// ── STEP 1: ID NUMBER SUBMITTED ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_id') {
    $id_input = trim($_POST['id_number'] ?? '');

    if (empty($id_input)) {
        $error = 'Please enter your ID number.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, first_name, middle_name, last_name,
                                       usc_id_number, barangay, city, province,
                                       contact_number, email
                                FROM users WHERE usc_id_number = ?");
        $stmt->bind_param('s', $id_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $step = 'confirm';
        } else {
            $_SESSION['prefill_id'] = $id_input;
            header('Location: register.php');
            exit();
        }
        $stmt->close();
    }
}

// ── STEP 2: USER CONFIRMS INFO, SIGN IN ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sign_in') {
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        $log = $conn->prepare("INSERT INTO visit_logs (user_id, time_in, status)
                               VALUES (?, NOW(), 'IN')");
        $log->bind_param('i', $user_id);
        $log->execute();
        $log->close();

        $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($uid, $fname, $lname);
        $stmt->fetch();
        $stmt->close();

        $_SESSION['user_id']    = $uid;
        $_SESSION['username']   = $fname . ' ' . $lname;
        $_SESSION['role']       = 'user';
        $_SESSION['flash']      = 'Welcome, ' . $fname . ' ' . $lname . '! You have been signed in.';
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php');
        exit();
    } else {
        $error = 'Something went wrong. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · CpE Contact Tracing</title>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=DM+Sans:wght@400;500;600;700&display=swap');

    :root {
        --bg:#f4f6fa; --surface:#ffffff; --surface-2:#f8fafc;
        --ink:#0c1a2e; --ink-soft:#3d4d63; --ink-muted:#7a8599;
        --border:#dde3ec; --border-strong:#c3cad6;
        --primary:#142a52; --primary-hover:#0a1a3a; --primary-soft:#e4ecf7;
        --accent:#d4a017;
        --danger:#b54a3a; --danger-soft:#fbe9e5;
        --font-display:'Fraunces', Georgia, serif;
        --font-body:'DM Sans', -apple-system, sans-serif;
        --r:8px; --r-lg:16px;
    }

    * { box-sizing:border-box; margin:0; padding:0; }
    body {
        font-family: var(--font-body);
        font-size: 15px; line-height: 1.55;
        color: var(--ink); background: var(--bg);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
        display: grid; place-items: center;
        padding: 32px 24px;
        position: relative; overflow-x: hidden;
    }
    body::before {
        content:""; position:absolute;
        top:-200px; right:-200px;
        width:500px; height:500px;
        background: radial-gradient(circle, var(--primary-soft) 0%, transparent 70%);
        pointer-events:none;
    }
    body::after {
        content:""; position:absolute;
        bottom:-250px; left:-250px;
        width:550px; height:550px;
        background: radial-gradient(circle, rgba(212,160,23,.10) 0%, transparent 70%);
        pointer-events:none;
    }

    .container { position:relative; z-index:1; width:100%; max-width:520px; }

    .auth-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 44px 40px 36px;
        box-shadow: 0 12px 32px rgba(12,26,46,.10);
    }

    .brand { display:flex; align-items:center; gap:12px; margin-bottom:32px; }
    .brand-mark {
        width:40px; height:40px;
        background: var(--primary); color:#fff;
        display: grid; place-items: center;
        border-radius: var(--r);
        font-family: var(--font-display);
        font-weight: 600; font-size: 17px;
        position: relative;
    }
    .brand-mark::after {
        content: ""; position: absolute;
        left: 8px; right: 8px; bottom: -4px;
        height: 3px;
        background: var(--accent);
        border-radius: 2px;
    }
    .brand-text { font-family: var(--font-display); font-weight: 600; font-size: 1.05rem; line-height:1.1; }
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
        font-size: 2.25rem;
        line-height: 1.1;
        letter-spacing: -0.01em;
        margin-bottom: 10px;
        font-variation-settings: "opsz" 96;
    }
    .lede { color: var(--ink-soft); font-size: 15px; margin-bottom: 28px; max-width: 46ch; }

    .alert {
        padding: 12px 16px;
        border-radius: var(--r);
        border: 1px solid;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-error { background: var(--danger-soft); border-color: #ecc1b9; color: var(--danger); }

    .field { margin-bottom: 20px; }
    .field label {
        display: block;
        font-size: 13px; font-weight: 600;
        color: var(--ink);
        margin-bottom: 8px;
    }
    .field input[type="text"] {
        width: 100%;
        padding: 12px 14px;
        font-family: var(--font-body);
        font-size: 15px;
        color: var(--ink);
        background: var(--surface);
        border: 1px solid var(--border-strong);
        border-radius: var(--r);
        transition: border-color .15s, box-shadow .15s;
    }
    .field input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }
    .field .hint { color: var(--ink-muted); font-size: 12px; margin-top: 6px; }

    .btn {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: var(--r);
        border: 1px solid transparent;
        font-family: var(--font-body);
        font-size: 14px; font-weight: 600;
        cursor: pointer; text-decoration: none;
        transition: background .15s, color .15s, border-color .15s, transform .05s;
        white-space: nowrap;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-secondary {
        background: var(--surface);
        color: var(--ink);
        border-color: var(--border-strong);
    }
    .btn-secondary:hover { background: var(--surface-2); }
    .btn-block { width: 100%; }

    .card-footer {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        font-size: 14px;
        color: var(--ink-soft);
    }
    .card-footer a {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
        border-bottom: 1px solid transparent;
        transition: border-color .15s;
    }
    .card-footer a:hover { border-bottom-color: currentColor; }

    /* ── Confirm step ── */
    .info-grid {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--r);
        margin-bottom: 24px;
        overflow: hidden;
    }
    .info-row {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 16px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border);
    }
    .info-row:last-child { border-bottom: none; }
    .info-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--ink-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        align-self: center;
    }
    .info-value { font-size: 14px; color: var(--ink); font-weight: 500; }
    .info-value.mono { font-family: ui-monospace, 'SF Mono', Menlo, monospace; font-size: 13px; }

    .action-row { display: flex; gap: 10px; align-items: stretch; }
    .action-row form { flex: 1; }

    .back-link {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
    }
    .back-link a {
        color: var(--ink-muted);
        text-decoration: none;
        border-bottom: 1px solid transparent;
        transition: color .15s, border-color .15s;
    }
    .back-link a:hover { color: var(--primary); border-bottom-color: var(--primary); }

    @media (max-width: 480px) {
        .auth-card { padding: 32px 24px; }
        h1 { font-size: 1.875rem; }
        .info-row { grid-template-columns: 1fr; gap: 4px; }
        .action-row { flex-direction: column; }
    }
    </style>
</head>
<body>

<div class="container">
    <div class="auth-card">
        <div class="brand">
            <div class="brand-mark">C</div>
            <div class="brand-text">
                Contact Tracing
                <small>Dept. of Computer Engineering · USC</small>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 'id'): ?>
        <!-- ───────────── STEP 1: Enter ID ───────────── -->
            <h1>Sign in</h1>
            <p class="lede">Enter your USC ID number to continue. Don't have one? You can register as a guest.</p>

            <form method="POST" action="user.php">
                <input type="hidden" name="action" value="check_id">
                <div class="field">
                    <label for="id_number">ID Number</label>
                    <input type="text" id="id_number" name="id_number"
                           placeholder="e.g. 20220001234" autofocus>
                    <span class="hint">Type your USC ID number, or leave blank to register as a guest.</span>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Continue →</button>
            </form>

            <div class="card-footer">
                No USC ID? <a href="register.php">Register as a guest or visitor</a>
            </div>

        <?php elseif ($step === 'confirm'): ?>
        <!-- ───────────── STEP 2: Confirm Info ───────────── -->
            <h1>Welcome back</h1>
            <p class="lede">We found your record. Verify that your information below is correct before signing in.</p>

            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">USC ID</span>
                    <span class="info-value mono"><?= htmlspecialchars($user['usc_id_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value">
                        <?= htmlspecialchars(trim($user['first_name'] . ' ' .
                            ($user['middle_name'] ?: '') . ' ' . $user['last_name'])) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value">
                        <?= htmlspecialchars($user['barangay'] . ', ' . $user['city'] . ', ' . $user['province']) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact</span>
                    <span class="info-value"><?= htmlspecialchars($user['contact_number']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                </div>
            </div>

            <div class="action-row">
                <form method="POST" action="user.php">
                    <input type="hidden" name="action"  value="sign_in">
                    <input type="hidden" name="user_id" value="<?= intval($user['user_id']) ?>">
                    <button type="submit" class="btn btn-primary btn-block">✓ Confirm &amp; sign in</button>
                </form>
                <a href="user.php" class="btn btn-secondary">Not me</a>
            </div>

        <?php endif; ?>
    </div>

    <div class="back-link">
        <a href="index.php">← Back to home</a>
    </div>
</div>

</body>
</html>
