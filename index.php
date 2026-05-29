<?php
session_start();
require_once 'dbconfig.php';

// Redirect admin away from this page
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin.php');
    exit();
}

// ── Fetch last signed-in user (non-admin only) ───────────────
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

// ── Fetch last signed-out user ───────────────────────────────
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

// ── Flash messages passed via session from redirects ─────────
$flash      = $_SESSION['flash']      ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>CpE Contact Tracing</title>
</head>
<body>

<h1>CpE Department – Contact Tracing</h1>
<hr>

<?php if ($flash): ?>
    <p style="color:<?= $flash_type === 'error' ? 'red' : 'green' ?>;">
        <strong><?= htmlspecialchars($flash) ?></strong>
    </p>
<?php endif; ?>

<!-- ── Status display ────────────────────────────────────────── -->
<table cellpadding="6">
    <tr>
        <td><strong>Last Signed In:</strong></td>
        <td><?= $last_signin_name ? htmlspecialchars($last_signin_name) : '<em>None yet</em>' ?></td>
    </tr>
    <tr>
        <td><strong>Last Signed Out:</strong></td>
        <td><?= $last_signout_name ? htmlspecialchars($last_signout_name) : '<em>None yet</em>' ?></td>
    </tr>
</table>

<hr>

<!-- ── Main Buttons ──────────────────────────────────────────── -->
<p>
    <a href="user.php">
        <button style="font-size:1.1em; padding:14px 40px;">SIGN IN</button>
    </a>
    &nbsp;&nbsp;&nbsp;
    <a href="signout.php">
        <button style="font-size:1.1em; padding:14px 40px;">SIGN OUT</button>
    </a>
</p>

</body>
</html>