<?php
/* ====================================================================
   admin.php — Single-file admin console
   All styles are inlined via render_styles() below. No external CSS.
   Routing via ?page=  →  dashboard | search | logs | users
   ==================================================================== */

session_start();
require_once 'dbconfig.php';

/* ── HARDCODED ADMIN CREDENTIALS ─────────────────────────────
   Per project spec: "The credentials (user name and password)
   may be hardcoded already in the app."
   ──────────────────────────────────────────────────────────── */
const ADMIN_USERNAME = 'cpe_admin@usc.edu.ph';
const ADMIN_PASSWORD = 'cpe_admin_12345';

/* ── LOGOUT ──────────────────────────────────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit();
}

/* ── LOGIN POST HANDLER ─────────────────────────────────────── */
$login_error  = '';
$is_logged_in = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$is_logged_in
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'login') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $login_error = 'Please enter both username and password.';
    } elseif (hash_equals(ADMIN_USERNAME, $username)
              && hash_equals(ADMIN_PASSWORD, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = 1;
        $_SESSION['username'] = ADMIN_USERNAME;
        $_SESSION['role']     = 'admin';
        header('Location: admin.php');
        exit();
    } else {
        $login_error = 'Invalid username or password.';
    }
}

/* ====================================================================
   CRUD ACTION HANDLERS for the Manage Users page
   These must run BEFORE any HTML is sent so we can redirect after a save.
   ==================================================================== */
$crud_error = '';   // shown on the detail/edit page if save fails

// — UPDATE user — submitted from the edit form
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'user_update'
    && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {

    $uid            = intval($_POST['user_id'] ?? 0);
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

    if ($uid <= 0) {
        $crud_error = 'Invalid user.';
    } elseif ($user_type === '' || $first_name === '' || $last_name === ''
              || $barangay === '' || $city === '' || $province === ''
              || $contact_number === '' || $email === '') {
        $crud_error = 'Please fill in all required fields.';
    } else {
        // Check duplicate USC ID against OTHER users
        $usc_id_val = ($usc_id === '') ? null : $usc_id;
        if ($usc_id_val !== null) {
            $chk = $conn->prepare("SELECT user_id FROM users
                                   WHERE usc_id_number = ? AND user_id <> ?");
            $chk->bind_param('si', $usc_id_val, $uid);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $crud_error = 'That USC ID is already used by another user.';
            }
            $chk->close();
        }

        if ($crud_error === '') {
            $upd = $conn->prepare("UPDATE users SET
                usc_id_number = ?, user_type = ?, first_name = ?, middle_name = ?,
                last_name = ?, barangay = ?, city = ?, province = ?,
                contact_number = ?, email = ?
                WHERE user_id = ?");
            $upd->bind_param('ssssssssssi',
                $usc_id_val, $user_type, $first_name, $middle_name, $last_name,
                $barangay, $city, $province, $contact_number, $email, $uid);

            if ($upd->execute()) {
                $_SESSION['flash']      = "User '{$first_name} {$last_name}' has been updated.";
                $_SESSION['flash_type'] = 'success';
                $upd->close();
                header('Location: admin.php?page=users&id=' . $uid);
                exit();
            } else {
                $crud_error = 'Could not save changes. Please try again.';
            }
            $upd->close();
        }
    }
}

// — DELETE user — submitted from confirm form on the detail page
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'user_delete'
    && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {

    $uid = intval($_POST['user_id'] ?? 0);
    if ($uid > 0) {
        // Delete visit_logs first to satisfy the FK constraint
        $conn->query("DELETE FROM visit_logs WHERE user_id = " . $uid);

        $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $del->bind_param('i', $uid);
        if ($del->execute()) {
            $_SESSION['flash']      = 'User deleted along with their visit history.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash']      = 'Could not delete that user.';
            $_SESSION['flash_type'] = 'error';
        }
        $del->close();
    }
    header('Location: admin.php?page=users');
    exit();
}

// — FORCE SIGN-OUT — admin closes a stuck open visit
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'force_signout'
    && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {

    $vid = intval($_POST['visit_id'] ?? 0);
    if ($vid > 0) {
        $upd = $conn->prepare("UPDATE visit_logs
                               SET time_out = NOW(), status = 'OUT'
                               WHERE visit_id = ? AND status = 'IN'");
        $upd->bind_param('i', $vid);
        $upd->execute();
        $upd->close();
        $_SESSION['flash']      = 'Visit closed.';
        $_SESSION['flash_type'] = 'success';
    }
    header('Location: admin.php?page=logs');
    exit();
}

/* ── Sidebar nav helper ─────────────────────────────────────── */
function nav_link(string $current, string $target, string $label, ?string $target_qs = null): string {
    $href   = $target_qs ? "admin.php?page={$target_qs}" : "admin.php";
    $active = ($current === $target) ? ' active' : '';
    return "<a href=\"{$href}\" class=\"nav-link{$active}\">" . htmlspecialchars($label) . "</a>";
}

/* ====================================================================
   STYLES  —  rendered into each <head>
   ==================================================================== */
function render_styles() { ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=DM+Sans:wght@400;500;600;700&display=swap');

:root {
    /* Palette */
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
    --warning:       #a87a1f;
    --warning-soft:  #faf0d7;

    /* Spacing */
    --s-1: 4px;  --s-2: 8px;  --s-3: 12px;  --s-4: 16px;
    --s-5: 24px; --s-6: 32px; --s-7: 48px;

    /* Typography */
    --font-display: 'Fraunces', Georgia, serif;
    --font-body:    'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;

    /* Radii */
    --r-sm: 4px; --r: 8px; --r-lg: 12px;

    /* Shadows */
    --shadow-lg: 0 12px 32px rgba(26,31,28,.10);
}

* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    font-family: var(--font-body);
    font-size: 15px;
    line-height: 1.55;
    color: var(--ink);
    background: var(--bg);
    -webkit-font-smoothing: antialiased;
}

h1, h2, h3, h4 {
    font-family: var(--font-display);
    font-weight: 500;
    line-height: 1.15;
    letter-spacing: -0.01em;
    color: var(--ink);
}
h1 { font-size: 2.25rem;  font-variation-settings: "opsz" 96, "SOFT" 50; }
h2 { font-size: 1.625rem; font-variation-settings: "opsz" 48; }
h3 { font-size: 1.15rem;  font-variation-settings: "opsz" 24; font-weight: 600; }
h4 { font-size: 1rem;     font-variation-settings: "opsz" 14; font-weight: 600; }

a {
    color: var(--primary);
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color .15s;
}
a:hover { border-bottom-color: currentColor; }

/* ===== APP BAR ===== */
.appbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: var(--s-4) var(--s-6);
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.brand { display: flex; align-items: center; gap: var(--s-3); }
.brand-mark {
    width: 36px; height: 36px;
    background: var(--primary); color: #fff;
    display: grid; place-items: center;
    border-radius: var(--r);
    font-family: var(--font-display);
    font-weight: 600; font-size: 16px;
    letter-spacing: -0.02em;
}
.brand-text {
    font-family: var(--font-display);
    font-weight: 600; font-size: 1.05rem;
    line-height: 1.1; color: var(--ink);
}
.brand-text small {
    display: block;
    font-family: var(--font-body);
    font-size: 11px; font-weight: 500;
    letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--ink-muted); margin-top: 3px;
}
.user-block {
    display: flex; align-items: center; gap: var(--s-3);
    font-size: 14px; color: var(--ink-soft);
}

/* ===== LAYOUT ===== */
.layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    min-height: calc(100vh - 72px);
}
.sidebar {
    background: var(--surface);
    border-right: 1px solid var(--border);
    padding: var(--s-5) var(--s-3);
}
.sidebar h5 {
    font-family: var(--font-body);
    text-transform: uppercase;
    font-size: 11px; font-weight: 600;
    letter-spacing: 0.08em;
    color: var(--ink-muted);
    padding: 0 var(--s-3) var(--s-2);
    margin-top: var(--s-4);
}
.sidebar h5:first-child { margin-top: 0; }
.sidebar .nav-link {
    display: block;
    padding: 10px var(--s-3);
    border-radius: var(--r);
    color: var(--ink-soft);
    font-weight: 500; font-size: 14px;
    margin-bottom: 2px;
    transition: background .15s, color .15s;
}
.sidebar .nav-link:hover {
    background: var(--bg); color: var(--ink);
    border-bottom-color: transparent;
}
.sidebar .nav-link.active {
    background: var(--primary-soft);
    color: var(--primary);
    font-weight: 600;
    border-bottom-color: transparent;
}

.content {
    padding: var(--s-6) var(--s-7);
    max-width: 1200px;
    width: 100%;
}
.content-header { margin-bottom: var(--s-6); }
.content-header .eyebrow {
    color: var(--ink-muted);
    font-size: 12px; font-weight: 600;
    letter-spacing: 0.08em; text-transform: uppercase;
    margin-bottom: var(--s-2);
}
.content-header .subtitle {
    color: var(--ink-soft);
    margin-top: var(--s-2);
    font-size: 15px;
    max-width: 60ch;
}

/* ===== STAT CARDS ===== */
.stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--s-4);
    margin-bottom: var(--s-6);
}
.stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: var(--s-5);
    position: relative; overflow: hidden;
}
.stat-card .label {
    font-size: 12px; color: var(--ink-muted);
    font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.06em;
}
.stat-card .value {
    font-family: var(--font-display);
    font-size: 2.75rem; font-weight: 500;
    margin-top: var(--s-2); color: var(--ink);
    line-height: 1;
    font-variation-settings: "opsz" 96;
}
.stat-card .delta {
    font-size: 13px; color: var(--ink-soft);
    margin-top: var(--s-3);
}
.stat-card.accent::before {
    content: ""; position: absolute;
    top: 0; left: 0; width: 4px; height: 100%;
    background: var(--primary);
}

/* ===== PANEL ===== */
.panel {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    overflow: hidden;
    margin-bottom: var(--s-5);
}
.panel-header {
    padding: var(--s-4) var(--s-5);
    border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between;
    align-items: center; gap: var(--s-3);
}
.panel-body { padding: var(--s-5); }
.panel-body.flush { padding: 0; }

/* ===== TABLE ===== */
.table { width: 100%; border-collapse: collapse; font-size: 14px; }
.table th {
    text-align: left;
    padding: var(--s-3) var(--s-5);
    font-weight: 600; color: var(--ink-muted);
    text-transform: uppercase; font-size: 11px;
    letter-spacing: 0.06em;
    background: var(--surface-2);
    border-bottom: 1px solid var(--border);
}
.table td {
    padding: var(--s-4) var(--s-5);
    border-bottom: 1px solid var(--border);
    color: var(--ink-soft); vertical-align: middle;
}
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: var(--surface-2); }
.table td strong { color: var(--ink); font-weight: 600; }

.empty-state {
    padding: var(--s-7) var(--s-5);
    text-align: center;
    color: var(--ink-muted);
    font-style: italic;
}

/* ===== BADGES ===== */
.badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 3px 10px; border-radius: 99px;
    font-size: 12px; font-weight: 600;
    letter-spacing: 0.02em;
}
.badge::before {
    content: ""; width: 6px; height: 6px;
    border-radius: 50%; background: currentColor;
}
.badge.in      { background: var(--success-soft); color: var(--success); }
.badge.out     { background: #e8eaf0;             color: var(--ink-muted); }
.badge.muted   { background: var(--bg);           color: var(--ink-muted); }

/* ===== BUTTONS ===== */
.btn {
    display: inline-flex; align-items: center; justify-content: center;
    gap: var(--s-2);
    padding: 10px 20px;
    border-radius: var(--r);
    border: 1px solid transparent;
    font-family: var(--font-body);
    font-size: 14px; font-weight: 600;
    cursor: pointer; text-decoration: none;
    transition: background .15s, color .15s, border-color .15s, transform .05s;
    white-space: nowrap;
}
.btn:active { transform: translateY(1px); }
.btn:hover  { border-bottom-color: transparent; }

.btn-primary   { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-hover); }

.btn-secondary { background: var(--surface); color: var(--ink); border-color: var(--border-strong); }
.btn-secondary:hover { background: var(--surface-2); }

.btn-ghost { background: transparent; color: var(--ink-soft); }
.btn-ghost:hover { background: var(--surface-2); color: var(--ink); }

.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #973e2f; }

.btn-sm { padding: 6px 14px; font-size: 13px; }
.btn-block { width: 100%; }

/* ===== FORMS ===== */
.field { margin-bottom: var(--s-4); }
.field label {
    display: block;
    font-size: 13px; font-weight: 600;
    color: var(--ink);
    margin-bottom: var(--s-2);
}
.field input[type="text"],
.field input[type="email"],
.field input[type="password"],
.field input[type="date"],
.field input[type="datetime-local"],
.field input[type="number"],
.field select,
.field textarea {
    width: 100%;
    padding: 10px 14px;
    font-family: var(--font-body);
    font-size: 14px; color: var(--ink);
    background: var(--surface);
    border: 1px solid var(--border-strong);
    border-radius: var(--r);
    transition: border-color .15s, box-shadow .15s;
}
.field input:focus,
.field select:focus,
.field textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}

/* ===== ALERTS ===== */
.alert {
    padding: var(--s-3) var(--s-4);
    border-radius: var(--r);
    border: 1px solid;
    margin-bottom: var(--s-5);
    font-size: 14px;
}
.alert-success { background: var(--success-soft); border-color: #b9dac5; color: var(--success); }
.alert-error   { background: var(--danger-soft);  border-color: #ecc1b9; color: var(--danger); }

/* ===== LOGIN ===== */
.login-shell {
    min-height: 100vh;
    display: grid; place-items: center;
    background: var(--bg);
    padding: var(--s-5);
    position: relative; overflow: hidden;
}
.login-shell::before {
    content: ""; position: absolute;
    top: -200px; right: -200px;
    width: 500px; height: 500px;
    background: radial-gradient(circle, var(--primary-soft) 0%, transparent 70%);
    pointer-events: none;
}
.login-shell::after {
    content: ""; position: absolute;
    bottom: -250px; left: -250px;
    width: 550px; height: 550px;
    background: radial-gradient(circle, rgba(212,160,23,0.10) 0%, transparent 70%);
    pointer-events: none;
}
.login-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    padding: var(--s-7);
    width: 100%; max-width: 440px;
    position: relative; z-index: 1;
    box-shadow: var(--shadow-lg);
}
.login-card .logo {
    display: flex; align-items: center;
    gap: var(--s-3);
    margin-bottom: var(--s-6);
}
.login-card h1 { font-size: 1.875rem; margin-bottom: var(--s-2); }
.login-card .sub {
    color: var(--ink-soft);
    margin-bottom: var(--s-5);
    font-size: 14px;
}
.login-card .footer-link {
    margin-top: var(--s-5);
    text-align: center;
    font-size: 13px;
    color: var(--ink-muted);
}

/* ===== SEARCH FILTERS ===== */
.filters {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--s-3);
    margin-bottom: var(--s-4);
}
.filters .field { margin-bottom: 0; }
.filters .field label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--ink-muted);
    font-weight: 600;
}
.filter-actions {
    display: flex;
    gap: var(--s-2);
    align-items: center;
    padding-top: var(--s-3);
    border-top: 1px solid var(--border);
}
.filter-summary {
    flex: 1;
    font-size: 13px;
    color: var(--ink-soft);
}
.filter-summary strong { color: var(--ink); }
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 99px;
    background: var(--primary-soft);
    color: var(--primary);
    font-size: 12px;
    font-weight: 600;
    margin-right: 4px;
}

@media (max-width: 900px) {
    .filters { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 500px) {
    .filters { grid-template-columns: 1fr; }
}

/* ===== ROW ACTIONS ===== */
.row-actions {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    flex-wrap: nowrap;
}
.row-actions a, .row-actions button {
    font-size: 12px;
    padding: 5px 10px;
    border-radius: var(--r-sm);
    text-decoration: none;
    font-weight: 600;
    border: 1px solid var(--border-strong);
    background: var(--surface);
    color: var(--ink-soft);
    cursor: pointer;
    font-family: var(--font-body);
    transition: background .15s, color .15s, border-color .15s;
}
.row-actions a:hover {
    background: var(--primary-soft);
    color: var(--primary);
    border-color: var(--primary-soft);
}
.row-actions .danger {
    color: var(--danger);
    border-color: #ecc1b9;
}
.row-actions .danger:hover {
    background: var(--danger-soft);
    border-color: var(--danger);
}

/* ===== DETAIL / EDIT LAYOUT ===== */
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--s-4);
}
.detail-grid .full { grid-column: 1 / -1; }

.detail-readout {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: var(--s-4) var(--s-5);
}
.detail-readout dl {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: var(--s-3) var(--s-4);
    margin: 0;
}
.detail-readout dt {
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    align-self: center;
}
.detail-readout dd {
    margin: 0;
    font-size: 14px;
    color: var(--ink);
    font-weight: 500;
}

/* ===== VISIT HISTORY LIST ===== */
.visit-history {
    margin: 0;
    padding: 0;
    list-style: none;
}
.visit-history li {
    display: flex;
    align-items: center;
    gap: var(--s-4);
    padding: var(--s-3) var(--s-5);
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.visit-history li:last-child { border-bottom: none; }
.visit-history .visit-date {
    font-weight: 600;
    color: var(--ink);
    min-width: 140px;
}
.visit-history .visit-times {
    flex: 1;
    color: var(--ink-soft);
    font-size: 13px;
}
.visit-history .visit-duration {
    color: var(--ink-muted);
    font-size: 12px;
    font-family: ui-monospace, 'SF Mono', Menlo, monospace;
    min-width: 80px;
    text-align: right;
}

/* ===== UTIL ===== */
.muted { color: var(--ink-muted); }
.mono  { font-family: ui-monospace, 'SF Mono', Menlo, monospace; font-size: 13px; }
</style>
<?php }

/* ====================================================================
   IF NOT LOGGED IN  →  RENDER LOGIN PAGE AND EXIT
   ==================================================================== */
if (!$is_logged_in) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign-in · CpE Contact Tracing</title>
    <?php render_styles(); ?>
</head>
<body>

<div class="login-shell">
    <div class="login-card">
        <div class="logo">
            <div class="brand-mark">C</div>
            <div class="brand-text">
                Contact Tracing
                <small>Dept. of Computer Engineering · USC</small>
            </div>
        </div>

        <h1>Administrator</h1>
        <p class="sub">Sign in to access the admin console.</p>

        <?php if ($login_error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>

        <form method="POST" action="admin.php" autocomplete="off">
            <input type="hidden" name="action" value="login">

            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autofocus required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>

        <div class="footer-link">
            <a href="index.php">← Back to main entry</a>
        </div>
    </div>
</div>

</body>
</html>
<?php
    exit();
}

/* ====================================================================
   LOGGED IN  →  ROUTE TO REQUESTED PAGE
   ==================================================================== */
$valid_pages = ['dashboard', 'search', 'logs', 'users'];
$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $valid_pages, true)) $page = 'dashboard';

$page_titles = [
    'dashboard' => 'Dashboard',
    'search'    => 'Search users',
    'logs'      => 'Visit logs',
    'users'     => 'Manage users',
];

/* ── Flash from previous redirect ─────────────────────────── */
$flash      = $_SESSION['flash']      ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);

/* ── Fetch dashboard data ─────────────────────────────────── */
if ($page === 'dashboard') {
    $total_users  = (int) $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
    $currently_in = (int) $conn->query("SELECT COUNT(*) FROM visit_logs WHERE status = 'IN'")->fetch_row()[0];
    $today_visits = (int) $conn->query("SELECT COUNT(*) FROM visit_logs WHERE DATE(time_in) = CURDATE()")->fetch_row()[0];
    $total_logs   = (int) $conn->query("SELECT COUNT(*) FROM visit_logs")->fetch_row()[0];

    $recent = $conn->query("
        SELECT u.first_name, u.last_name, u.user_type, u.usc_id_number,
               vl.time_in, vl.time_out, vl.status
        FROM visit_logs vl
        JOIN users u ON u.user_id = vl.user_id
        ORDER BY vl.time_in DESC
        LIMIT 10
    ");
}
/* ── Fetch search results ─────────────────────────────────── */
if ($page === 'search') {
    // Read the six filter params
    $f_id       = trim($_GET['id']       ?? '');
    $f_name     = trim($_GET['name']     ?? '');
    $f_barangay = trim($_GET['barangay'] ?? '');
    $f_city     = trim($_GET['city']     ?? '');
    $f_province = trim($_GET['province'] ?? '');
    $f_date     = trim($_GET['date']     ?? '');   // YYYY-MM-DD format

    $has_filters = ($f_id !== '' || $f_name !== '' || $f_barangay !== ''
                 || $f_city !== '' || $f_province !== '' || $f_date !== '');

    // Build the query dynamically
    $where  = [];
    $params = [];
    $types  = '';

    if ($f_id !== '') {
        $where[]  = "u.usc_id_number LIKE ?";
        $params[] = '%' . $f_id . '%';
        $types   .= 's';
    }
    if ($f_name !== '') {
        $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.middle_name LIKE ?)";
        $like     = '%' . $f_name . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'sss';
    }
    if ($f_barangay !== '') {
        $where[]  = "u.barangay LIKE ?";
        $params[] = '%' . $f_barangay . '%';
        $types   .= 's';
    }
    if ($f_city !== '') {
        $where[]  = "u.city LIKE ?";
        $params[] = '%' . $f_city . '%';
        $types   .= 's';
    }
    if ($f_province !== '') {
        $where[]  = "u.province LIKE ?";
        $params[] = '%' . $f_province . '%';
        $types   .= 's';
    }
    if ($f_date !== '') {
        // Match any user who had a visit on the given date
        $where[]  = "EXISTS (SELECT 1 FROM visit_logs vl
                             WHERE vl.user_id = u.user_id
                             AND DATE(vl.time_in) = ?)";
        $params[] = $f_date;
        $types   .= 's';
    }

    $sql = "SELECT u.user_id, u.usc_id_number, u.user_type,
                   u.first_name, u.middle_name, u.last_name,
                   u.barangay, u.city, u.province,
                   u.contact_number, u.email, u.date_registered,
                   (SELECT MAX(vl.time_in) FROM visit_logs vl WHERE vl.user_id = u.user_id) AS last_visit
            FROM users u";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY u.last_name ASC, u.first_name ASC LIMIT 200";

    $search_stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $search_stmt->bind_param($types, ...$params);
    }
    $search_stmt->execute();
    $search_results = $search_stmt->get_result();
    $result_count   = $search_results->num_rows;
}

/* ── Fetch visit logs ─────────────────────────────────────── */
if ($page === 'logs') {
    $l_from   = trim($_GET['from']   ?? '');   // YYYY-MM-DD
    $l_to     = trim($_GET['to']     ?? '');
    $l_name   = trim($_GET['name']   ?? '');
    $l_status = trim($_GET['status'] ?? '');   // 'IN' | 'OUT' | ''

    $where  = [];
    $params = [];
    $types  = '';

    if ($l_from !== '') {
        $where[]  = "DATE(vl.time_in) >= ?";
        $params[] = $l_from;
        $types   .= 's';
    }
    if ($l_to !== '') {
        $where[]  = "DATE(vl.time_in) <= ?";
        $params[] = $l_to;
        $types   .= 's';
    }
    if ($l_name !== '') {
        $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
        $like     = '%' . $l_name . '%';
        $params[] = $like; $params[] = $like;
        $types   .= 'ss';
    }
    if ($l_status === 'IN' || $l_status === 'OUT') {
        $where[]  = "vl.status = ?";
        $params[] = $l_status;
        $types   .= 's';
    }

    $log_has_filters = !empty($where);

    $sql = "SELECT vl.visit_id, vl.user_id, vl.time_in, vl.time_out, vl.status,
                   u.first_name, u.last_name, u.user_type, u.usc_id_number
            FROM visit_logs vl
            JOIN users u ON u.user_id = vl.user_id";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY vl.time_in DESC LIMIT 300";

    $log_stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $log_stmt->bind_param($types, ...$params);
    }
    $log_stmt->execute();
    $log_results = $log_stmt->get_result();
    $log_count   = $log_results->num_rows;
}

/* ── Fetch user list / single-user detail ─────────────────── */
if ($page === 'users') {
    $edit_id = intval($_GET['id'] ?? 0);

    if ($edit_id > 0) {
        // Single-user detail / edit
        $u_stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $u_stmt->bind_param('i', $edit_id);
        $u_stmt->execute();
        $edit_user = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();

        if (!$edit_user) {
            $_SESSION['flash']      = 'That user no longer exists.';
            $_SESSION['flash_type'] = 'error';
            header('Location: admin.php?page=users');
            exit();
        }

        // Visit history for that user (most-recent 20)
        $h_stmt = $conn->prepare("SELECT * FROM visit_logs
                                  WHERE user_id = ?
                                  ORDER BY time_in DESC LIMIT 20");
        $h_stmt->bind_param('i', $edit_id);
        $h_stmt->execute();
        $edit_user_visits = $h_stmt->get_result();
        $h_stmt->close();

        $visit_total = (int) $conn->query(
            "SELECT COUNT(*) FROM visit_logs WHERE user_id = " . $edit_id
        )->fetch_row()[0];

    } else {
        // User list with optional name filter
        $u_filter = trim($_GET['q'] ?? '');
        $sql      = "SELECT u.*,
                            (SELECT COUNT(*) FROM visit_logs vl WHERE vl.user_id = u.user_id) AS visit_count,
                            (SELECT MAX(vl.time_in) FROM visit_logs vl WHERE vl.user_id = u.user_id) AS last_visit
                     FROM users u";
        if ($u_filter !== '') {
            $sql .= " WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.usc_id_number LIKE ?";
        }
        $sql .= " ORDER BY u.last_name ASC, u.first_name ASC LIMIT 200";

        $u_stmt = $conn->prepare($sql);
        if ($u_filter !== '') {
            $like = '%' . $u_filter . '%';
            $u_stmt->bind_param('sss', $like, $like, $like);
        }
        $u_stmt->execute();
        $user_results = $u_stmt->get_result();
        $user_count   = $user_results->num_rows;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_titles[$page]) ?> · CpE Contact Tracing</title>
    <?php render_styles(); ?>
</head>
<body>

<header class="appbar">
    <div class="brand">
        <div class="brand-mark">C</div>
        <div class="brand-text">
            Contact Tracing
            <small>Department of Computer Engineering</small>
        </div>
    </div>
    <div class="user-block">
        <span class="muted">Signed in as</span>
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        <a href="admin.php?action=logout" class="btn btn-ghost btn-sm">Sign out</a>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">
        <h5>Overview</h5>
        <?= nav_link($page, 'dashboard', 'Dashboard') ?>

        <h5>Records</h5>
        <?= nav_link($page, 'search', 'Search users', 'search') ?>
        <?= nav_link($page, 'logs',   'Visit logs',   'logs')   ?>
        <?= nav_link($page, 'users',  'Manage users', 'users')  ?>
    </aside>

    <main class="content">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash_type === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <?php /* ============ PAGE: DASHBOARD ============ */ ?>
    <?php if ($page === 'dashboard'): ?>

        <div class="content-header">
            <div class="eyebrow">Admin console</div>
            <h1>Dashboard</h1>
            <p class="subtitle">A quick overview of office activity. Use the sidebar to search records, view logs, or manage registered users.</p>
        </div>

        <div class="stats">
            <div class="stat-card accent">
                <div class="label">Currently signed in</div>
                <div class="value"><?= $currently_in ?></div>
                <div class="delta">people inside the office right now</div>
            </div>
            <div class="stat-card">
                <div class="label">Visits today</div>
                <div class="value"><?= $today_visits ?></div>
                <div class="delta">sign-ins recorded since 00:00</div>
            </div>
            <div class="stat-card">
                <div class="label">Registered users</div>
                <div class="value"><?= $total_users ?></div>
                <div class="delta">total people on file</div>
            </div>
            <div class="stat-card">
                <div class="label">Visit log entries</div>
                <div class="value"><?= $total_logs ?></div>
                <div class="delta">all-time entries &amp; exits</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Recent activity</h3>
                <a href="admin.php?page=logs" class="btn btn-ghost btn-sm">View all logs →</a>
            </div>
            <div class="panel-body flush">
                <?php if ($recent && $recent->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>USC ID</th>
                            <th>Time in</th>
                            <th>Time out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($r = $recent->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong></td>
                            <td><?= htmlspecialchars(ucfirst($r['user_type'])) ?></td>
                            <td class="mono"><?= htmlspecialchars($r['usc_id_number'] ?: '—') ?></td>
                            <td><?= date('M j, Y · g:i A', strtotime($r['time_in'])) ?></td>
                            <td><?= $r['time_out']
                                    ? date('M j, Y · g:i A', strtotime($r['time_out']))
                                    : '<span class="muted">—</span>' ?></td>
                            <td>
                                <?php if ($r['status'] === 'IN'): ?>
                                    <span class="badge in">Inside</span>
                                <?php else: ?>
                                    <span class="badge out">Signed out</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state">
                        No visit logs yet.<br>
                        Once people start signing in, recent activity will appear here.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php /* ============ PAGE: SEARCH USERS ============ */ ?>
    <?php elseif ($page === 'search'): ?>

        <div class="content-header">
            <div class="eyebrow">Records</div>
            <h1>Search users</h1>
            <p class="subtitle">Filter registered users by ID number, name, address, or date of entry. Combine multiple filters to narrow the results.</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Filters</h3>
                <?php if ($has_filters): ?>
                    <a href="admin.php?page=search" class="btn btn-ghost btn-sm">Clear all</a>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <form method="GET" action="admin.php" autocomplete="off">
                    <input type="hidden" name="page" value="search">

                    <div class="filters">
                        <div class="field">
                            <label for="f_id">ID number</label>
                            <input type="text" id="f_id" name="id"
                                   value="<?= htmlspecialchars($f_id) ?>"
                                   placeholder="e.g. 20220001">
                        </div>
                        <div class="field">
                            <label for="f_name">Name</label>
                            <input type="text" id="f_name" name="name"
                                   value="<?= htmlspecialchars($f_name) ?>"
                                   placeholder="First, middle, or last">
                        </div>
                        <div class="field">
                            <label for="f_barangay">Barangay</label>
                            <input type="text" id="f_barangay" name="barangay"
                                   value="<?= htmlspecialchars($f_barangay) ?>"
                                   placeholder="e.g. Lahug">
                        </div>
                        <div class="field">
                            <label for="f_city">City / Town</label>
                            <input type="text" id="f_city" name="city"
                                   value="<?= htmlspecialchars($f_city) ?>"
                                   placeholder="e.g. Cebu City">
                        </div>
                        <div class="field">
                            <label for="f_province">Province</label>
                            <input type="text" id="f_province" name="province"
                                   value="<?= htmlspecialchars($f_province) ?>"
                                   placeholder="e.g. Cebu">
                        </div>
                        <div class="field">
                            <label for="f_date">Date of entry</label>
                            <input type="date" id="f_date" name="date"
                                   value="<?= htmlspecialchars($f_date) ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-summary">
                            <?php if ($has_filters): ?>
                                Showing <strong><?= $result_count ?></strong>
                                <?= $result_count === 1 ? 'result' : 'results' ?>
                                <?php if ($result_count === 200): ?>
                                    <span class="muted">(showing first 200, refine filters to narrow)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">No filters applied — showing all <strong><?= $result_count ?></strong> registered users</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($has_filters): ?>
                            <a href="admin.php?page=search" class="btn btn-secondary btn-sm">Reset</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Results</h3>
                <span class="muted" style="font-size:13px;">
                    <?= $result_count ?> <?= $result_count === 1 ? 'user' : 'users' ?>
                </span>
            </div>
            <div class="panel-body flush">
                <?php if ($result_count > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>USC ID</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Last visit</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($r = $search_results->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($r['last_name']) ?>,
                                        <?= htmlspecialchars($r['first_name']) ?></strong>
                                <?php if (!empty($r['middle_name'])): ?>
                                    <span class="muted"> <?= htmlspecialchars($r['middle_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars(ucfirst($r['user_type'])) ?></td>
                            <td class="mono"><?= htmlspecialchars($r['usc_id_number'] ?: '—') ?></td>
                            <td>
                                <?= htmlspecialchars($r['barangay']) ?>,
                                <?= htmlspecialchars($r['city']) ?>,
                                <?= htmlspecialchars($r['province']) ?>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($r['contact_number']) ?></div>
                                <div class="muted" style="font-size:12px;"><?= htmlspecialchars($r['email']) ?></div>
                            </td>
                            <td>
                                <?php if ($r['last_visit']): ?>
                                    <?= date('M j, Y', strtotime($r['last_visit'])) ?>
                                    <div class="muted" style="font-size:12px;">
                                        <?= date('g:i A', strtotime($r['last_visit'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">Never</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state">
                        <?php if ($has_filters): ?>
                            No users match your filters.<br>
                            Try removing some criteria or check your spelling.
                        <?php else: ?>
                            No registered users yet.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php /* ============ PAGE: VISIT LOGS ============ */ ?>
    <?php elseif ($page === 'logs'): ?>

        <div class="content-header">
            <div class="eyebrow">Records</div>
            <h1>Visit logs</h1>
            <p class="subtitle">Every sign-in and sign-out event, newest first. Filter by date range, name, or status to narrow the list.</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Filters</h3>
                <?php if (!empty($log_has_filters)): ?>
                    <a href="admin.php?page=logs" class="btn btn-ghost btn-sm">Clear all</a>
                <?php endif; ?>
            </div>
            <div class="panel-body">
                <form method="GET" action="admin.php" autocomplete="off">
                    <input type="hidden" name="page" value="logs">

                    <div class="filters">
                        <div class="field">
                            <label for="l_from">From date</label>
                            <input type="date" id="l_from" name="from"
                                   value="<?= htmlspecialchars($l_from) ?>">
                        </div>
                        <div class="field">
                            <label for="l_to">To date</label>
                            <input type="date" id="l_to" name="to"
                                   value="<?= htmlspecialchars($l_to) ?>">
                        </div>
                        <div class="field">
                            <label for="l_name">Name contains</label>
                            <input type="text" id="l_name" name="name"
                                   value="<?= htmlspecialchars($l_name) ?>"
                                   placeholder="First or last name">
                        </div>
                        <div class="field">
                            <label for="l_status">Status</label>
                            <select id="l_status" name="status">
                                <option value="">All</option>
                                <option value="IN"  <?= $l_status === 'IN'  ? 'selected' : '' ?>>Currently inside</option>
                                <option value="OUT" <?= $l_status === 'OUT' ? 'selected' : '' ?>>Signed out</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-summary">
                            <?php if (!empty($log_has_filters)): ?>
                                Showing <strong><?= $log_count ?></strong>
                                <?= $log_count === 1 ? 'entry' : 'entries' ?>
                                <?php if ($log_count === 300): ?>
                                    <span class="muted">(showing first 300, narrow the date range to see more)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="muted">No filters applied — showing the most recent <strong><?= $log_count ?></strong> entries</span>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Log entries</h3>
                <span class="muted" style="font-size:13px;">
                    <?= $log_count ?> <?= $log_count === 1 ? 'entry' : 'entries' ?>
                </span>
            </div>
            <div class="panel-body flush">
                <?php if ($log_count > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>USC ID</th>
                            <th>Time in</th>
                            <th>Time out</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($r = $log_results->fetch_assoc()):
                        $dur = '';
                        if ($r['time_out']) {
                            $secs = strtotime($r['time_out']) - strtotime($r['time_in']);
                            if ($secs >= 3600) {
                                $dur = floor($secs / 3600) . 'h ' . floor(($secs % 3600) / 60) . 'm';
                            } elseif ($secs >= 60) {
                                $dur = floor($secs / 60) . 'm';
                            } else {
                                $dur = $secs . 's';
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <a href="admin.php?page=users&id=<?= intval($r['user_id']) ?>">
                                    <strong><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></strong>
                                </a>
                            </td>
                            <td><?= htmlspecialchars(ucfirst($r['user_type'])) ?></td>
                            <td class="mono"><?= htmlspecialchars($r['usc_id_number'] ?: '—') ?></td>
                            <td>
                                <?= date('M j, Y', strtotime($r['time_in'])) ?>
                                <div class="muted" style="font-size:12px;">
                                    <?= date('g:i A', strtotime($r['time_in'])) ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($r['time_out']): ?>
                                    <?= date('M j, Y', strtotime($r['time_out'])) ?>
                                    <div class="muted" style="font-size:12px;">
                                        <?= date('g:i A', strtotime($r['time_out'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= $dur ?: '<span class="muted">—</span>' ?></td>
                            <td>
                                <?php if ($r['status'] === 'IN'): ?>
                                    <span class="badge in">Inside</span>
                                <?php else: ?>
                                    <span class="badge out">Signed out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ($r['status'] === 'IN'): ?>
                                    <form method="POST" action="admin.php" style="display:inline;"
                                          onsubmit="return confirm('Force sign-out for this visit?');">
                                        <input type="hidden" name="action"   value="force_signout">
                                        <input type="hidden" name="visit_id" value="<?= intval($r['visit_id']) ?>">
                                        <button type="submit" class="row-actions" style="padding:0;border:none;background:none;">
                                            <span style="font-size:12px;padding:5px 10px;border-radius:var(--r-sm);border:1px solid var(--border-strong);background:var(--surface);color:var(--ink-soft);font-weight:600;">
                                                Force out
                                            </span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state">
                        <?php if (!empty($log_has_filters)): ?>
                            No entries match your filters.
                        <?php else: ?>
                            No visit logs yet. Activity will appear here once people start signing in.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php /* ============ PAGE: MANAGE USERS ============ */ ?>
    <?php elseif ($page === 'users'): ?>

        <?php if ($edit_id > 0 && !empty($edit_user)): ?>
        <!-- ───────────── DETAIL / EDIT VIEW ───────────── -->

            <div class="content-header">
                <div class="eyebrow"><a href="admin.php?page=users">← All users</a> · Records</div>
                <h1><?= htmlspecialchars($edit_user['first_name'] . ' ' . $edit_user['last_name']) ?></h1>
                <p class="subtitle">Review and update this user's information, or remove them from the records.</p>
            </div>

            <?php if (!empty($crud_error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($crud_error) ?></div>
            <?php endif; ?>

            <div class="panel">
                <div class="panel-header">
                    <h3>Edit information</h3>
                    <span class="muted" style="font-size:13px;">
                        Registered <?= date('M j, Y', strtotime($edit_user['date_registered'])) ?>
                    </span>
                </div>
                <div class="panel-body">
                    <form method="POST" action="admin.php?page=users&id=<?= intval($edit_user['user_id']) ?>">
                        <input type="hidden" name="action"  value="user_update">
                        <input type="hidden" name="user_id" value="<?= intval($edit_user['user_id']) ?>">

                        <div class="detail-grid">
                            <div class="field">
                                <label>USC ID number</label>
                                <input type="text" name="usc_id_number"
                                       value="<?= htmlspecialchars($edit_user['usc_id_number'] ?? '') ?>"
                                       placeholder="Leave blank if guest">
                            </div>
                            <div class="field">
                                <label>User type</label>
                                <select name="user_type" required>
                                    <?php foreach (['student', 'faculty', 'staff', 'guest'] as $t): ?>
                                        <option value="<?= $t ?>" <?= $edit_user['user_type'] === $t ? 'selected' : '' ?>>
                                            <?= ucfirst($t) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>First name</label>
                                <input type="text" name="first_name"
                                       value="<?= htmlspecialchars($edit_user['first_name']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Middle name</label>
                                <input type="text" name="middle_name"
                                       value="<?= htmlspecialchars($edit_user['middle_name'] ?? '') ?>">
                            </div>
                            <div class="field full">
                                <label>Last name</label>
                                <input type="text" name="last_name"
                                       value="<?= htmlspecialchars($edit_user['last_name']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Barangay</label>
                                <input type="text" name="barangay"
                                       value="<?= htmlspecialchars($edit_user['barangay']) ?>" required>
                            </div>
                            <div class="field">
                                <label>City / Town</label>
                                <input type="text" name="city"
                                       value="<?= htmlspecialchars($edit_user['city']) ?>" required>
                            </div>
                            <div class="field full">
                                <label>Province</label>
                                <input type="text" name="province"
                                       value="<?= htmlspecialchars($edit_user['province']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Contact number</label>
                                <input type="text" name="contact_number"
                                       value="<?= htmlspecialchars($edit_user['contact_number']) ?>" required>
                            </div>
                            <div class="field">
                                <label>Email</label>
                                <input type="email" name="email"
                                       value="<?= htmlspecialchars($edit_user['email']) ?>" required>
                            </div>
                        </div>

                        <div class="filter-actions" style="margin-top:24px;">
                            <div class="filter-summary">&nbsp;</div>
                            <a href="admin.php?page=users" class="btn btn-ghost btn-sm">Cancel</a>
                            <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Visit history</h3>
                    <span class="muted" style="font-size:13px;">
                        <?= $visit_total ?> total <?= $visit_total === 1 ? 'visit' : 'visits' ?>
                        <?php if ($visit_total > 20): ?>(showing latest 20)<?php endif; ?>
                    </span>
                </div>
                <div class="panel-body flush">
                    <?php if ($edit_user_visits && $edit_user_visits->num_rows > 0): ?>
                        <ul class="visit-history">
                        <?php while ($v = $edit_user_visits->fetch_assoc()):
                            $dur = '';
                            if ($v['time_out']) {
                                $secs = strtotime($v['time_out']) - strtotime($v['time_in']);
                                if ($secs >= 3600) {
                                    $dur = floor($secs / 3600) . 'h ' . floor(($secs % 3600) / 60) . 'm';
                                } elseif ($secs >= 60) {
                                    $dur = floor($secs / 60) . 'm';
                                } else {
                                    $dur = $secs . 's';
                                }
                            }
                        ?>
                            <li>
                                <div class="visit-date"><?= date('M j, Y', strtotime($v['time_in'])) ?></div>
                                <div class="visit-times">
                                    In at <?= date('g:i A', strtotime($v['time_in'])) ?>
                                    <?php if ($v['time_out']): ?>
                                        · Out at <?= date('g:i A', strtotime($v['time_out'])) ?>
                                    <?php else: ?>
                                        · <span style="color:var(--success); font-weight:600;">still inside</span>
                                    <?php endif; ?>
                                </div>
                                <div class="visit-duration"><?= $dur ?: '—' ?></div>
                            </li>
                        <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <div class="empty-state">No visit history yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 style="color:var(--danger);">Danger zone</h3>
                </div>
                <div class="panel-body">
                    <div class="row between" style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:240px;">
                            <strong>Delete this user</strong>
                            <p class="muted" style="font-size:13px; margin-top:4px;">
                                Permanently removes the user's record <em>and</em> their entire visit history. This cannot be undone.
                            </p>
                        </div>
                        <form method="POST" action="admin.php"
                              onsubmit="return confirm('Delete <?= htmlspecialchars($edit_user['first_name'] . ' ' . $edit_user['last_name'], ENT_QUOTES) ?> and their visit history? This cannot be undone.');">
                            <input type="hidden" name="action"  value="user_delete">
                            <input type="hidden" name="user_id" value="<?= intval($edit_user['user_id']) ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete user</button>
                        </form>
                    </div>
                </div>
            </div>

        <?php else: ?>
        <!-- ───────────── LIST VIEW ───────────── -->

            <div class="content-header">
                <div class="eyebrow">Records</div>
                <h1>Manage users</h1>
                <p class="subtitle">View, edit, or remove any registered user. Click a name to open their profile.</p>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>All users</h3>
                    <form method="GET" action="admin.php" style="display:flex; gap:8px; align-items:center;">
                        <input type="hidden" name="page" value="users">
                        <input type="text" name="q"
                               value="<?= htmlspecialchars($u_filter ?? '') ?>"
                               placeholder="Search by name or ID…"
                               style="padding:6px 12px; font-size:13px; border:1px solid var(--border-strong); border-radius:var(--r); font-family:var(--font-body); width:240px;">
                        <button type="submit" class="btn btn-secondary btn-sm">Find</button>
                        <?php if (!empty($u_filter)): ?>
                            <a href="admin.php?page=users" class="btn btn-ghost btn-sm">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="panel-body flush">
                    <?php if ($user_count > 0): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>USC ID</th>
                                <th>City / Province</th>
                                <th>Visits</th>
                                <th>Last visit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($u = $user_results->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="admin.php?page=users&id=<?= intval($u['user_id']) ?>">
                                        <strong><?= htmlspecialchars($u['last_name'] . ', ' . $u['first_name']) ?></strong>
                                    </a>
                                    <div class="muted" style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td><?= htmlspecialchars(ucfirst($u['user_type'])) ?></td>
                                <td class="mono"><?= htmlspecialchars($u['usc_id_number'] ?: '—') ?></td>
                                <td>
                                    <?= htmlspecialchars($u['city']) ?>
                                    <div class="muted" style="font-size:12px;"><?= htmlspecialchars($u['province']) ?></div>
                                </td>
                                <td><?= intval($u['visit_count']) ?></td>
                                <td>
                                    <?php if ($u['last_visit']): ?>
                                        <?= date('M j, Y', strtotime($u['last_visit'])) ?>
                                    <?php else: ?>
                                        <span class="muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="row-actions">
                                        <a href="admin.php?page=users&id=<?= intval($u['user_id']) ?>">View / Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <?php if (!empty($u_filter)): ?>
                                No users match "<?= htmlspecialchars($u_filter) ?>".
                            <?php else: ?>
                                No registered users yet.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

    <?php endif; ?>

    </main>
</div>

</body>
</html>
