<?php
session_start();
require_once 'dbconfig.php';

$error   = '';
$success = '';

// Pre-fill ID if coming from user.php
$prefill_id = $_SESSION['prefill_id'] ?? '';
unset($_SESSION['prefill_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $usc_id         = trim($_POST['usc_id_number']  ?? '');
    $user_type      = trim($_POST['user_type']      ?? '');
    $first_name     = trim($_POST['first_name']     ?? '');
    $middle_name    = trim($_POST['middle_name']    ?? '');
    $last_name      = trim($_POST['last_name']      ?? '');
    $barangay       = trim($_POST['barangay']       ?? '');
    $city           = trim($_POST['city']           ?? '');
    $province       = trim($_POST['province']       ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email']          ?? '');

    if (empty($user_type) || empty($first_name) || empty($last_name) ||
        empty($barangay)  || empty($city)        || empty($province)  ||
        empty($contact_number) || empty($email)) {
        $error = 'Please fill in all required fields.';

    } elseif (!empty($usc_id)) {
        $chk = $conn->prepare("SELECT user_id FROM users WHERE usc_id_number = ?");
        $chk->bind_param('s', $usc_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = 'That USC ID is already registered.';
        }
        $chk->close();
    }

    if (empty($error)) {
        $usc_id_val = empty($usc_id) ? null : $usc_id;

        $stmt = $conn->prepare("INSERT INTO users
            (usc_id_number, user_type, first_name, middle_name, last_name,
             barangay, city, province, contact_number, email, date_registered)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param('ssssssssss',
            $usc_id_val, $user_type, $first_name, $middle_name, $last_name,
            $barangay, $city, $province, $contact_number, $email);

        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            $stmt->close();

            $log = $conn->prepare("INSERT INTO visit_logs (user_id, time_in, status) VALUES (?, NOW(), 'IN')");
            $log->bind_param('i', $new_user_id);
            $log->execute();
            $log->close();

            $_SESSION['user_id']    = $new_user_id;
            $_SESSION['username']   = $first_name . ' ' . $last_name;
            $_SESSION['role']       = 'user';
            $_SESSION['flash']      = 'Welcome, ' . $first_name . ' ' . $last_name . '! Registration complete and you are now signed in.';
            $_SESSION['flash_type'] = 'success';

            header('Location: index.php');
            exit();
        } else {
            $error = 'Registration failed. Please try again.';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register · CpE Contact Tracing</title>
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
        display: grid; place-items: start center;
        padding: 40px 24px;
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

    .container { position:relative; z-index:1; width:100%; max-width:680px; }

    .auth-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 44px 44px 36px;
        box-shadow: 0 12px 32px rgba(26,31,28,.10);
    }

    .brand { display:flex; align-items:center; gap:12px; margin-bottom:28px; }
    .brand-mark {
        width:40px; height:40px;
        background: var(--primary); color:#fff;
        display: grid; place-items: center;
        border-radius: var(--r);
        font-family: var(--font-display);
        font-weight:600; font-size:17px;
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
        margin-bottom: 8px;
        font-variation-settings: "opsz" 96;
    }
    .lede {
        color: var(--ink-soft);
        font-size: 15px;
        margin-bottom: 28px;
        max-width: 52ch;
    }

    .alert {
        padding: 12px 16px;
        border-radius: var(--r);
        border: 1px solid;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-error { background: var(--danger-soft); border-color: #ecc1b9; color: var(--danger); }

    .form-section { margin-bottom: 24px; }
    .form-section .section-title {
        font-family: var(--font-body);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--ink-muted);
        margin-bottom: 14px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border);
    }

    .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(2, 1fr);
    }
    .grid.three { grid-template-columns: repeat(3, 1fr); }
    .grid > .full { grid-column: 1 / -1; }

    .field { margin-bottom: 0; }
    .field label {
        display: block;
        font-size: 13px; font-weight: 600;
        color: var(--ink);
        margin-bottom: 6px;
    }
    .field label .req { color: var(--danger); font-weight: 700; }
    .field label .opt { color: var(--ink-muted); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }

    .field input[type="text"],
    .field input[type="email"],
    .field select {
        width: 100%;
        padding: 10px 14px;
        font-family: var(--font-body);
        font-size: 14px; color: var(--ink);
        background: var(--surface);
        border: 1px solid var(--border-strong);
        border-radius: var(--r);
        transition: border-color .15s, box-shadow .15s;
    }
    .field input:focus, .field select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }
    .field .hint { color: var(--ink-muted); font-size: 12px; margin-top: 6px; }

    .submit-row {
        display: flex; align-items: center;
        gap: 12px;
        padding-top: 24px;
        margin-top: 8px;
        border-top: 1px solid var(--border);
    }
    .submit-row .required-note {
        flex: 1;
        font-size: 12px;
        color: var(--ink-muted);
    }

    .btn {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px;
        padding: 11px 22px;
        border-radius: var(--r);
        border: 1px solid transparent;
        font-family: var(--font-body);
        font-size: 14px; font-weight: 600;
        cursor: pointer; text-decoration: none;
        transition: background .15s, transform .05s, border-color .15s;
        white-space: nowrap;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary { background: var(--primary); color: #fff; }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-ghost { background: transparent; color: var(--ink-soft); }
    .btn-ghost:hover { background: var(--surface-2); color: var(--ink); }

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

    @media (max-width: 600px) {
        .auth-card { padding: 32px 24px; }
        h1 { font-size: 1.875rem; }
        .grid, .grid.three { grid-template-columns: 1fr; }
        .submit-row { flex-direction: column-reverse; align-items: stretch; }
        .submit-row .btn { width: 100%; }
        .submit-row .required-note { text-align: center; }
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

        <h1>Register</h1>
        <p class="lede">First time here? Fill in your information below. You'll be automatically signed in once you complete registration.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <input type="hidden" name="action" value="register">

            <div class="form-section">
                <div class="section-title">Identification</div>
                <div class="grid">
                    <div class="field">
                        <label for="usc_id_number">USC ID Number <span class="opt">optional</span></label>
                        <input type="text" id="usc_id_number" name="usc_id_number"
                               value="<?= htmlspecialchars($prefill_id) ?>"
                               placeholder="Leave blank if not from USC">
                    </div>
                    <div class="field">
                        <label for="user_type">User Type <span class="req">*</span></label>
                        <select id="user_type" name="user_type" required>
                            <option value="">— Select —</option>
                            <option value="student">Student</option>
                            <option value="faculty">Faculty</option>
                            <option value="staff">Staff</option>
                            <option value="guest">Guest / Visitor</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">Full Name</div>
                <div class="grid three">
                    <div class="field">
                        <label for="first_name">First name <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="field">
                        <label for="middle_name">Middle name <span class="opt">optional</span></label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>
                    <div class="field">
                        <label for="last_name">Last name <span class="req">*</span></label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">Address</div>
                <div class="grid three">
                    <div class="field">
                        <label for="barangay">Barangay <span class="req">*</span></label>
                        <input type="text" id="barangay" name="barangay" required>
                    </div>
                    <div class="field">
                        <label for="city">City / Town <span class="req">*</span></label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="field">
                        <label for="province">Province <span class="req">*</span></label>
                        <input type="text" id="province" name="province" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="section-title">Contact</div>
                <div class="grid">
                    <div class="field">
                        <label for="contact_number">Phone number <span class="req">*</span></label>
                        <input type="text" id="contact_number" name="contact_number"
                               placeholder="e.g. 09171234567" required>
                    </div>
                    <div class="field">
                        <label for="email">Email <span class="req">*</span></label>
                        <input type="email" id="email" name="email"
                               placeholder="name@example.com" required>
                    </div>
                </div>
            </div>

            <div class="submit-row">
                <a href="index.php" class="btn btn-ghost">Cancel</a>
                <span class="required-note"><span style="color:var(--danger); font-weight:700;">*</span> indicates a required field</span>
                <button type="submit" class="btn btn-primary">Register &amp; sign in →</button>
            </div>
        </form>
    </div>

    <div class="back-link">
        <a href="index.php">← Back to home</a>
    </div>
</div>

</body>
</html>
