<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin.php' : 'user.php'));
    exit();
}

require_once 'dbconfig.php';

// ── HARDCODED ADMIN ID (replace later with hashed check) ────
define('ADMIN_ID', '6769420');

$error   = '';
$step    = 'id';          // 'id' → ask for ID first; 'confirm' → show retrieved info
$user    = null;

// ── STEP 1: ID NUMBER SUBMITTED ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_id') {
    $id_input = trim($_POST['id_number'] ?? '');

    if (empty($id_input)) {
        $error = 'Please enter your ID number.';

    // Admin check
    } elseif ($id_input === ADMIN_ID) {
        $_SESSION['role']     = 'admin';
        $_SESSION['user_id']  = 0;          // placeholder until real admin table is used
        $_SESSION['username'] = 'Admin';
        header('Location: admin.php');
        exit();

    // Check if USC ID exists in users table
    } else {
        $stmt = $conn->prepare("SELECT user_id, first_name, middle_name, last_name,
                                       usc_id_number, barangay, city, province,
                                       contact_number, email
                                FROM users WHERE usc_id_number = ?");
        $stmt->bind_param('s', $id_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Returning user – show info for confirmation
            $user = $result->fetch_assoc();
            $step = 'confirm';
        } else {
            // New user – send to registration page with ID pre-filled
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
        // Log the visit (time_in = now, status = IN)
        $log = $conn->prepare("INSERT INTO visit_logs (user_id, time_in, status)
                               VALUES (?, NOW(), 'IN')");
        $log->bind_param('i', $user_id);
        $log->execute();
        $log->close();

        // Re-fetch user info for session
        $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($uid, $fname, $lname);
        $stmt->fetch();
        $stmt->close();

        $_SESSION['user_id']  = $uid;
        $_SESSION['username'] = $fname . ' ' . $lname;
        $_SESSION['role']     = 'user';
        header('Location: user.php');
        exit();
    } else {
        $error = 'Something went wrong. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>CpE Contact Tracing – Sign In</title>
</head>
<body>

<h1>CpE Department – Contact Tracing</h1>
<hr>

<?php if ($error): ?>
    <p style="color:red;"><strong>Error:</strong> <?= htmlspecialchars($error) ?></p>
<?php endif; ?>


<?php if ($step === 'id'): ?>
<!-- ── STEP 1: Enter ID Number ─────────────────────────── -->
<h2>Sign In</h2>
<p>Enter your ID number to continue. If you are a guest or visitor without a USC ID, leave the field blank and click <em>Register as Guest</em>.</p>

<form method="POST" action="index.php">
    <input type="hidden" name="action" value="check_id">

    <label for="id_number">ID Number:</label><br>
    <input type="text" id="id_number" name="id_number" placeholder="e.g. 20220001234"><br><br>

    <button type="submit">Continue →</button>
</form>

<br>
<p>No USC ID? <a href="register.php">Register as a guest / visitor</a></p>


<?php elseif ($step === 'confirm'): ?>
<!-- ── STEP 2: Confirm Retrieved Information ───────────── -->
<h2>Confirm Your Information</h2>
<p>We found your record. Please verify that the information below is correct before signing in.</p>

<table border="1" cellpadding="6" cellspacing="0">
    <tr><th>USC ID Number</th> <td><?= htmlspecialchars($user['usc_id_number']) ?></td></tr>
    <tr><th>Full Name</th>     <td><?= htmlspecialchars($user['first_name'] . ' ' .
                                        ($user['middle_name'] ? $user['middle_name'] . ' ' : '') .
                                        $user['last_name']) ?></td></tr>
    <tr><th>Barangay</th>      <td><?= htmlspecialchars($user['barangay']) ?></td></tr>
    <tr><th>City</th>          <td><?= htmlspecialchars($user['city']) ?></td></tr>
    <tr><th>Province</th>      <td><?= htmlspecialchars($user['province']) ?></td></tr>
    <tr><th>Contact Number</th><td><?= htmlspecialchars($user['contact_number']) ?></td></tr>
    <tr><th>Email</th>         <td><?= htmlspecialchars($user['email']) ?></td></tr>
</table>

<br>

<!-- If correct, sign in -->
<form method="POST" action="index.php" style="display:inline;">
    <input type="hidden" name="action"  value="sign_in">
    <input type="hidden" name="user_id" value="<?= intval($user['user_id']) ?>">
    <button type="submit">✔ Yes, this is correct – Sign In</button>
</form>

&nbsp;&nbsp;

<!-- If wrong, go back -->
<form method="POST" action="index.php" style="display:inline;">
    <input type="hidden" name="action" value="">
    <button type="submit">✖ Not me – Go Back</button>
</form>

<?php endif; ?>

</body>
</html>