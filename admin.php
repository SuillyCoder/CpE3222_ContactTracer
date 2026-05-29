<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}
    echo "THIS IS THE ADMIN PAGE!";
?>

<!DOCTYPE html>
<head>
    <title>
    </title>
</head>
<body>
    <form action="logout.php" method="POST">
        
        
        <button type="submit" name="submit_button">LOGOUT</button>
    </form>
</body>
</html>

