<?php
session_start();
require_once 'config/database.php';
if ($_SESSION['user_rol'] !== 'admin') { die("Accés denegat."); }

// Consulta que calcula hores totals i filtra els que fan menys de 8 hores
$query = "SELECT u.nom, SUM(TIMESTAMPDIFF(HOUR, r.entrada, r.sortida)) as hores_totals 
          FROM usuaris u 
          LEFT JOIN registres r ON u.id = r.usuari_id 
          WHERE u.rol = 'empleat'
          GROUP BY u.id 
          HAVING hores_totals IS NULL OR hores_totals < 8";
$alertes = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html>
<body>
    <h2>⚠️ Llista Vermella d'Incompliment</h2>
    <a href="projectes.php">Anar a Projectes</a> | <a href="logout.php">Tancar sessió</a><hr>
    <table border="1" cellpadding="10" style="border-collapse:collapse; background:#ffe6e6;">
        <tr style="background:#ff4d4d; color:white;">
            <th>Empleat</th>
            <th>Hores Treballades</th>
            <th>Estat</th>
        </tr>
        <?php foreach($alertes as $a): ?>
        <tr>
            <td><?php echo htmlspecialchars($a['nom']); ?></td>
            <td><?php echo $a['hores_totals'] ?? 0; ?>h</td>
            <td style="color:red; font-weight:bold;">Insuficient (&lt; 8h)</td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>