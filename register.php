<?php
session_start();
require_once 'dbconfig.php';

$error   = '';
$success = '';

// Pre-fill ID if coming from index.php
$prefill_id = $_SESSION['prefill_id'] ?? '';
unset($_SESSION['prefill_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $usc_id         = trim($_POST['usc_id_number']  ?? '');
    $user_type      = trim($_POST['user_type']       ?? '');
    $first_name     = trim($_POST['first_name']      ?? '');
    $middle_name    = trim($_POST['middle_name']     ?? '');
    $last_name      = trim($_POST['last_name']       ?? '');
    $barangay       = trim($_POST['barangay']        ?? '');
    $city           = trim($_POST['city']            ?? '');
    $province       = trim($_POST['province']        ?? '');
    $contact_number = trim($_POST['contact_number']  ?? '');
    $email          = trim($_POST['email']           ?? '');

    // Validation
    if (empty($user_type) || empty($first_name) || empty($last_name) ||
        empty($barangay)  || empty($city)        || empty($province)  ||
        empty($contact_number) || empty($email)) {
        $error = 'Please fill in all required fields.';

    } elseif (!empty($usc_id)) {
        // Check for duplicate USC ID
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

            // Immediately log the sign-in
            $log = $conn->prepare("INSERT INTO visit_logs (user_id, time_in, status) VALUES (?, NOW(), 'IN')");
            $log->bind_param('i', $new_user_id);
            $log->execute();
            $log->close();

            // Set session
            $_SESSION['user_id']  = $new_user_id;
            $_SESSION['username'] = $first_name . ' ' . $last_name;
            $_SESSION['role']     = 'user';
            $_SESSION['flash']      = 'Successfully signed in! Welcome, ' . $first_name . ' ' . $last_name . '.';
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
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>CpE Contact Tracing – Register</title>
</head>
<body>

<h1>CpE Department – Contact Tracing</h1>
<hr>
<h2>New User Registration</h2>
<p>Please fill in your information. Fields marked with <strong>*</strong> are required.</p>

<?php if ($error): ?>
    <p style="color:red;"><strong>Error:</strong> <?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="POST" action="register.php">
    <input type="hidden" name="action" value="register">

    <table cellpadding="6">
        <tr>
            <td><label for="usc_id_number">USC ID Number:</label></td>
            <td>
                <input type="text" id="usc_id_number" name="usc_id_number"
                       value="<?= htmlspecialchars($prefill_id) ?>"
                       placeholder="Leave blank if guest/visitor">
                <small>(Optional for guests)</small>
            </td>
        </tr>
        <tr>
            <td><label for="user_type">User Type: *</label></td>
            <td>
                <select id="user_type" name="user_type" required>
                    <option value="">-- Select --</option>
                    <option value="student">Student</option>
                    <option value="faculty">Faculty</option>
                    <option value="staff">Staff</option>
                    <option value="guest">Guest</option>
                    <option value="visitor">Visitor</option>
                </select>
            </td>
        </tr>
        <tr>
            <td><label for="first_name">First Name: *</label></td>
            <td><input type="text" id="first_name" name="first_name" required></td>
        </tr>
        <tr>
            <td><label for="middle_name">Middle Name:</label></td>
            <td><input type="text" id="middle_name" name="middle_name" placeholder="Optional"></td>
        </tr>
        <tr>
            <td><label for="last_name">Last Name: *</label></td>
            <td><input type="text" id="last_name" name="last_name" required></td>
        </tr>
        <tr>
            <td><label for="barangay">Barangay: *</label></td>
            <td><input type="text" id="barangay" name="barangay" required></td>
        </tr>
        <tr>
            <td><label for="city">City / Town: *</label></td>
            <td><input type="text" id="city" name="city" required></td>
        </tr>
        <tr>
            <td><label for="province">Province: *</label></td>
            <td><input type="text" id="province" name="province" required></td>
        </tr>
        <tr>
            <td><label for="contact_number">Contact Number: *</label></td>
            <td><input type="text" id="contact_number" name="contact_number" required></td>
        </tr>
        <tr>
            <td><label for="email">Email Address: *</label></td>
            <td><input type="email" id="email" name="email" required></td>
        </tr>
    </table>

    <br>
    <button type="submit">Register &amp; Sign In →</button>
    <a href="index.php">&nbsp;&nbsp;← Go Back</a>
</form>

</body>
</html>