<?php
session_start();
include "config/database.php";

// Proses login
if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM user_admin WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $admin = $result->fetch_assoc();
        if(password_verify($password, $admin['password'])){
            $_SESSION['admin'] = $admin['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Password salah";
        }
    } else {
        $error = "Username tidak ditemukan";
    }
}

// Ambil semua akun admin
$akun_list = $conn->query("SELECT username FROM user_admin");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Admin</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="login-page">
<div class="container login-container">
    <h2 class="login-title">Login Admin</h2>

    <form class="login-form" method="POST">
        <input class="login-input" type="text" name="username" placeholder="Username" required>
        <input class="login-input" type="password" name="password" placeholder="Password" required>
        <button class="login-btn" type="submit" name="login">Login</button>
    </form>

    <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

    <p class="info">Belum punya akun? <a href="create_user.php">Daftar di sini</a></p>
    <br>

    <h3>Daftar Akun Admin</h3>
    <ul class="login-list">
        <?php while($row = $akun_list->fetch_assoc()): ?>
            <li><?= $row['username'] ?></li>
        <?php endwhile; ?>
    </ul>

</div>
</body>

</html>
