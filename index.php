<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $contrasenya = $_POST['contrasenya'];

    $stmt = $pdo->prepare("SELECT * FROM usuaris WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($contrasenya, $user['contrasenya'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['user_rol'] = $user['rol'];
        
        header("Location: " . ($user['rol'] === 'admin' ? 'projectes.php' : 'fitxar.php'));
        exit;
    } else {
        $error = "Credencials incorrectes.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Login - Tracking</title></head>
<body>
    <h2>Control de Temps - Accés</h2>
    <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST">
        Email: <input type="email" name="email" required><br><br>
        Contrasenya: <input type="password" name="contrasenya" required><br><br>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>