<?php
session_start();
require_once 'config/database.php';

// Seguretat: Si no és administrador, deneguem l'accés immediatament
if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') { 
    die("Accés denegat. No tens permisos d'administrador."); 
}

// Consulta optimitzada (Tasca 6): Demanem columnes específiques i calculem la suma d'hores reals
$query_projectes = "SELECT p.nom, p.hores_pressupostades, 
                    COALESCE(SUM(TIMESTAMPDIFF(HOUR, r.entrada, r.sortida)), 0) as hores_reals 
                    FROM projectes p 
                    LEFT JOIN registres r ON p.id = r.projecte_id 
                    GROUP BY p.id";

$projectes = $pdo->query($query_projectes)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Temps - Projectes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>📊 Control de Costos i Projectes</h2>
    <p>Benvingut, <?php echo htmlspecialchars($_SESSION['user_nom']); ?> (Administrador)</p>
    
    <a href="llista_vermella.php">Veure Llista Vermella</a> | <a href="logout.php">Tancar sessió</a>
    <hr>
    
    <table>
        <thead>
            <tr>
                <th>Projecte</th>
                <th>Hores Pressupostades</th>
                <th>Hores Reals Consumides</th>
                <th>Estat de Balanç</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($projectes as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['nom']); ?></td>
                <td><?php echo $p['hores_pressupostades']; ?>h</td>
                <td><?php echo $p['hores_reals']; ?>h</td>
                <td>
                    <?php if($p['hores_reals'] > $p['hores_pressupostades']): ?>
                        <span style="color:#d9534f; font-weight:bold;">⚠️ Perdent diners!</span>
                    <?php else: ?>
                        <span style="color:#5cb85c; font-weight:bold;">✅ Dins del pressupost</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>