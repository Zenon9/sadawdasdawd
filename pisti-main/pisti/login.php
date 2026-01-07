<?php
session_start(); 
include "db.php";

$error = "";

// Handle login submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (isset($_POST['admin_login'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // ========================
        // ADMIN LOGIN
        // ========================
        $stmt = $conn->prepare("SELECT username, password FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $adminResult = $stmt->get_result();

        if ($adminResult->num_rows === 1) {
            $admin = $adminResult->fetch_assoc();

            if (password_verify($password, $admin['password'])) {
                $_SESSION['username'] = $admin['username'];
                $_SESSION['role'] = 'admin';
                header("Location: index.php");
                exit();
            } else {
                $error = "Incorrect password!";
            }
        } else {
            $error = "Admin account not found!";
        }

    } elseif (isset($_POST['student_login'])) {
        // ========================
        // AUTOMATIC STUDENT ACCESS
        // ========================
        $_SESSION['username'] = "Guest Student"; // Optional default name
        $_SESSION['role'] = 'student';
        $_SESSION['student_id'] = 0; // Default ID for guest access
        header("Location: student_transaction.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Login Page</title>
<link rel="stylesheet" href="Untitled-1.css" />
</head>
<body>
<header>De La Salle John Bosco College</header>

<main>
    <div class="logo">
        <img src="image.png.jpg" alt="logo">
        <h2>HiEd's</h2>
        <h1>Book Borrowing</h1>
        <h1>System</h1>
    </div>

    <div class="login-wrapper">
        <div class="login-box">
            <h1>Log In</h1>

            <?php if ($error != ""): ?>
                <p style="color:red;"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>

            <form class="login-form" method="post">
                <!-- Admin login fields -->
                <input type="text" name="username" placeholder="Admin Username" />
                <input type="password" name="password" placeholder="Password" />

                <!-- Admin login button -->
                <button type="submit" name="admin_login">Admin Login</button>

                <!-- Student automatic login button -->
                <button type="submit" name="student_login">Student Access</button>
            </form>

        </div>
    </div>
</main>

</body>
</html>