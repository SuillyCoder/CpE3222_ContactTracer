<?php
session_start();
require_once 'dbconfig.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sign_out') {
    $name_input = trim($_POST['full_name'] ?? '');
    $id_input   = trim($_POST['id_number'] ?? '');

    if (empty($name_input)) {
        $error = 'Please enter your name.';
    } else {
        $uid      = null;
        $db_fname = null;
        $db_lname = null;

        // Try by USC ID first if provided
        if (!empty($id_input)) {
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name
                                    FROM users WHERE usc_id_number = ?");
            $stmt->bind_param('s', $id_input);
            $stmt->execute();
            $stmt->bind_result($uid, $db_fname, $db_lname);
            $stmt->fetch();
            $stmt->close();

            if (empty($uid)) {
                $error = 'No user found with that ID number.';
            }
        } else {
            // Search by name
            $parts = explode(' ', $name_input, 2);
            $fname_search = $parts[0];
            $lname_search = $parts[1] ?? '';

            $stmt = $conn->prepare("SELECT user_id, first_name, last_name
                                    FROM users
                                    WHERE first_name LIKE ? AND last_name LIKE ?
                                    LIMIT 1");
            $fname_like = '%' . $fname_search . '%';
            $lname_like = '%' . $lname_search . '%';
            $stmt->bind_param('ss', $fname_like, $lname_like);
            $stmt->execute();
            $stmt->bind_result($uid, $db_fname, $db_lname);
            $stmt->fetch();
            $stmt->close();

            if (empty($uid)) {
                $error = 'No user found matching that name. Try entering your ID number too.';
            }
        }

        // Look for an open visit log
        if (!empty($uid) && empty($error)) {
            $log_stmt = $conn->prepare("SELECT visit_id FROM visit_logs
                                        WHERE user_id = ? AND status = 'IN'
                                        ORDER BY time_in DESC LIMIT 1");
            $log_stmt->bind_param('i', $uid);
            $log_stmt->execute();
            $log_stmt->bind_result($visit_id);
            $log_stmt->fetch();
            $log_stmt->close();

            if (empty($visit_id)) {
                $error = $db_fname . ' ' . $db_lname . ' is not currently signed in.';
            } else {
                $upd = $conn->prepare("UPDATE visit_logs
                                       SET time_out = NOW(), status = 'OUT'
                                       WHERE visit_id = ?");
                $upd->bind_param('i', $visit_id);
                $upd->execute();
                $upd->close();

                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $uid) {
                    session_destroy();
                    session_start();
                }

                $_SESSION['flash']      = $db_fname . ' ' . $db_lname . ' has been signed out successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: index.php');
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Out · CpE Contact Tracing</title>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=DM+Sans:wght@400;500;600;700&display=swap');

    :root {
        --bg:#f4f6fa; --surface:#ffffff; --surface-2:#f8fafc;
        --ink:#0c1a2e; --ink-soft:#3d4d63; --ink-muted:#7a8599;
        --border:#dde3ec; --border-strong:#c3cad6;
        --primary:#142a52; --primary-hover:#0a1a3a; --primary-soft:#e4ecf7;
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
        box-shadow: 0 12px 32px rgba(26,31,28,.10);
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

    .field { margin-bottom: 18px; }
    .field label {
        display: block;
        font-size: 13px; font-weight: 600;
        color: var(--ink);
        margin-bottom: 8px;
    }
    .field label .req { color: var(--danger); font-weight: 700; }
    .field label .opt { color: var(--ink-muted); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
    .field input[type="text"] {
        width: 100%;
        padding: 12px 14px;
        font-family: var(--font-body);
        font-size: 15px; color: var(--ink);
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

    .btn {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px;
        padding: 12px 22px;
        border-radius: var(--r);
        border: 1px solid transparent;
        font-family: var(--font-body);
        font-size: 14px; font-weight: 600;
        cursor: pointer; text-decoration: none;
        transition: background .15s, transform .05s;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-ghost { background: transparent; color: var(--ink-soft); }
    .btn-ghost:hover { background: var(--surface-2); color: var(--ink); }
    .btn-block { width: 100%; }

    .submit-row {
        display: flex;
        gap: 10px;
        align-items: stretch;
        margin-top: 8px;
    }
    .submit-row form { flex: 1; }

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
        .submit-row { flex-direction: column; }
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

        <h1>Sign out</h1>
        <p class="lede">Enter your name to sign out. Your ID number is optional but helps if there are multiple people with the same name.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="signout.php">
            <input type="hidden" name="action" value="sign_out">

            <div class="field">
                <label for="full_name">Full name <span class="req">*</span></label>
                <input type="text" id="full_name" name="full_name"
                       placeholder="e.g. Juan Dela Cruz"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       autofocus required>
            </div>

            <div class="field">
                <label for="id_number">USC ID Number <span class="opt">optional</span></label>
                <input type="text" id="id_number" name="id_number"
                       placeholder="e.g. 20220001234"
                       value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
            </div>

            <div class="submit-row">
                <a href="index.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary" style="flex:1;">Sign out →</button>
            </div>
        </form>
    </div>

    <div class="back-link">
        <a href="index.php">← Back to home</a>
    </div>
</div>

</body>
</html>
