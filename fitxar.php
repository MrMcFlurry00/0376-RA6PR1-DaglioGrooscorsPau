<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$usuari_id = $_SESSION['user_id'];

// Comprovar si té un registre actiu sense tancar
$stmt = $pdo->prepare("SELECT * FROM registres WHERE usuari_id = :uid AND sortida IS NULL");
$stmt->execute(['uid' => $usuari_id]);
$registre_actiu = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['entrada']) && !$registre_actiu) {
        $projecte_id = $_POST['projecte_id'];
        $stmt = $pdo->prepare("INSERT INTO registres (usuari_id, projecte_id, entrada) VALUES (:uid, :pid, NOW())");
        $stmt->execute(['uid' => $usuari_id, 'pid' => $projecte_id]);
    } elseif (isset($_POST['sortida']) && $registre_actiu) {
        $stmt = $pdo->prepare("UPDATE registres SET sortida = NOW() WHERE id = :rid");
        $stmt->execute(['rid' => $registre_actiu['id']]);
    }
    header("Location: fitxar.php");
    exit;
}

$projectes = $pdo->query("SELECT * FROM projectes")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Temps</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Benvingut, <?php echo htmlspecialchars($_SESSION['user_nom']); ?></h2>
    <a href="logout.php">Tancar sessió</a><hr>
    
    <form method="POST">
        <?php if (!$registre_actiu): ?>
            <label>Selecciona Projecte:</label>
            <select name="projecte_id">
                <?php foreach($projectes as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nom']); ?></option>
                <?php endforeach; ?>
            </select><br><br>
            <button type="submit" name="entrada" style="background:green;color:white;">Fitxar Entrada</button>
        <?php else: ?>
            <p style="color:blue;">Treballant actualment...</p>
            <button type="submit" name="sortida" style="background:red;color:white;">Fitxar Sortida</button>
        <?php endif; ?>
    </form>
</body>
</html>