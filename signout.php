<?php
session_start();
require_once 'dbconfig.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sign_out') {
    $name_input = trim($_POST['full_name']   ?? '');
    $id_input   = trim($_POST['id_number']   ?? '');

    if (empty($name_input)) {
        $error = 'Please enter your name.';
    } else {
        $uid      = null;
        $db_fname = null;
        $db_lname = null;

        // ── Try to find user by USC ID first (if provided) ──
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
            // ── No ID provided: search by name ──────────────
            // Split input into parts to match first + last name
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

        // ── If user found, look for an open visit log ────────
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
                $error = htmlspecialchars($db_fname . ' ' . $db_lname) . ' is not currently signed in.';
            } else {
                // ── Sign them out ────────────────────────────
                $upd = $conn->prepare("UPDATE visit_logs
                                       SET time_out = NOW(), status = 'OUT'
                                       WHERE visit_id = ?");
                $upd->bind_param('i', $visit_id);
                $upd->execute();
                $upd->close();

                // Clear session if it belongs to this user
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $uid) {
                    session_destroy();
                    session_start();
                }

                // Pass flash message back to index.php
                $_SESSION['flash']      = htmlspecialchars($db_fname . ' ' . $db_lname) . ' has been signed out successfully.';
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
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>CpE Contact Tracing – Sign Out</title>
</head>
<body>

<h1>CpE Department – Contact Tracing</h1>
<hr>
<h2>Sign Out</h2>
<p>Enter your name to sign out. Your ID number is optional but helps if there are multiple people with the same name.</p>

<?php if ($error): ?>
    <p style="color:red;"><strong>Error:</strong> <?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" action="signout.php">
    <input type="hidden" name="action" value="sign_out">

    <table cellpadding="6">
        <tr>
            <td><label for="full_name">Full Name: <strong>*</strong></label></td>
            <td>
                <input type="text" id="full_name" name="full_name"
                       placeholder="e.g. Juan Dela Cruz"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       required>
            </td>
        </tr>
        <tr>
            <td><label for="id_number">USC ID Number:</label></td>
            <td>
                <input type="text" id="id_number" name="id_number"
                       placeholder="Optional"
                       value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
            </td>
        </tr>
    </table>

    <br>
    <button type="submit" style="font-size:1.1em; padding:10px 30px;">Sign Out →</button>
    &nbsp;&nbsp;
    <a href="index.php">← Cancel</a>
</form>

</body>
</html>