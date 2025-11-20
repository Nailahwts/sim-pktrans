<?php
session_start();
include "config/database.php";

// Proses buat akun baru
if(isset($_POST['create'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Cek apakah username sudah ada
    $stmt = $conn->prepare("SELECT * FROM user_admin WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $error = "Username sudah terdaftar!";
    } else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert akun baru
        $stmt_insert = $conn->prepare("INSERT INTO user_admin (username, password) VALUES (?, ?)");
        $stmt_insert->bind_param("ss", $username, $hashed_password);
        if($stmt_insert->execute()){
            $success = "Akun berhasil dibuat!";
        } else {
            $error = "Gagal membuat akun.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Buat Akun Admin</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="login-page">
<div class="container login-container">
    <h2 class="login-title">Buat Akun Admin</h2>

    <form class="login-form" method="POST">
        <input class="login-input" type="text" name="username" placeholder="Username" required>
        <input class="login-input" type="password" name="password" placeholder="Password" required>
        <button class="login-btn" type="submit" name="create">Buat Akun</button>
    </form>

    <?php 
    if(isset($error)) echo "<p class='error'>$error</p>"; 
    if(isset($success)) echo "<p class='success'>$success</p>"; 
    ?>

    <p class="info">Sudah punya akun? <a href="login.php">Login di sini</a></p>
</div>
</body>
</html>
