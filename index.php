<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $contrasenya = $_POST['contrasenya'] ?? '';

    if (empty($email) || empty($contrasenya)) {
        $error = "Has d'omplir tots els camps.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuaris WHERE email = :email AND actiu = 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($contrasenya, $user['contrasenya'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_rol'] = $user['rol'];
            session_regenerate_id(true);
            header("Location: " . ($user['rol'] === 'admin' ? 'dashboard.php' : 'fitxar.php'));
            exit;
        } else {
            $error = "Credencials incorrectes. Comprova el teu email i contrasenya.";
        }
    }
}

// Si ja està loguejat, redirigir
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['user_rol'] === 'admin' ? 'dashboard.php' : 'fitxar.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accés - Control Horari</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="icon">⏱️</div>
            <h1>Control Horari</h1>
            <p>Gestió de temps i projectes</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <span>⚠️ <?php echo htmlspecialchars($error); ?></span>
                <button type="button" class="alert-close" data-dismiss>×</button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="form-group">
                <label for="email">📧 Correu electrònic</label>
                <input type="email" id="email" name="email" class="form-control" required autofocus placeholder="nom@empresa.com">
            </div>
            <div class="form-group">
                <label for="contrasenya">🔒 Contrasenya</label>
                <input type="password" id="contrasenya" name="contrasenya" class="form-control" required placeholder="Introdueix la teva contrasenya">
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                Entrar →
            </button>
        </form>

        <div class="login-info">
            <strong>🔑 Credencials de demo</strong><br>
            Admin: <code>cap@empresa.com</code> / <code>password</code><br>
            Empleat: <code>joan@empresa.com</code> / <code>password</code>
        </div>
    </div>
</body>
</html>
