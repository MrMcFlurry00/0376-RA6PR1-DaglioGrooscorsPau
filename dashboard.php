<?php
$page_title = 'Panell General';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

// Acció admin: tancar registre obert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tancar_registre'])) {
    $rid = (int)$_POST['registre_id'];
    $sortida = $_POST['sortida_dt'] ?? null;
    $notes = trim($_POST['notes_admin'] ?? '');
    if (tancar_registre($pdo, $rid, $sortida ?: null, $notes)) {
        set_flash('success', 'Registre tancat correctament.');
    } else {
        set_flash("danger", "No s'ha pogut tancar el registre.");
    }
    header("Location: dashboard.php");
    exit;
}
$flash = check_flash();

// === KPI GLOBALS ===
$hores_avui = hores_avui_total();
$treballant_ara = empleats_treballant();

$stmt = $pdo->query("SELECT COUNT(*) as c FROM usuaris WHERE rol='empleat' AND actiu=1");
$total_empleats = (int)$stmt->fetch()['c'];

$stmt = $pdo->query("SELECT COUNT(*) as c FROM projectes WHERE actiu=1");
$total_projectes = (int)$stmt->fetch()['c'];

$stmt = $pdo->query("SELECT COUNT(*) as c FROM alertes WHERE resolta=0");
$alertes_obertes = (int)$stmt->fetch()['c'];

$stmt = $pdo->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600, 0) as h
                     FROM registres WHERE YEARWEEK(entrada, 1) = YEARWEEK(CURDATE(), 1)");
$hores_setmana_total = (float)$stmt->fetch()['h'];

// Hores per dia (últims 7 dies)
$stmt = $pdo->query("SELECT DATE(entrada) as dia,
                            COALESCE(SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600, 0) as h
                     FROM registres
                     WHERE entrada >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                     GROUP BY DATE(entrada)
                     ORDER BY dia ASC");
$hpd = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reomplir dies buits
$dies_complets = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $dies_complets[$d] = 0;
}
foreach ($hpd as $r) {
    $dies_complets[$r['dia']] = (float)$r['h'];
}
$labels_dies = [];
$valors_dies = [];
foreach ($dies_complets as $d => $h) {
    $labels_dies[] = "'" . date('D d/m', strtotime($d)) . "'";
    $valors_dies[] = round($h, 1);
}

// Hores per projecte (aquesta setmana)
$stmt = $pdo->query("SELECT p.nom, p.color, COALESCE(SUM(TIMESTAMPDIFF(SECOND, r.entrada, IFNULL(r.sortida, NOW()))) / 3600, 0) as h
                     FROM registres r
                     JOIN projectes p ON r.projecte_id = p.id
                     WHERE YEARWEEK(r.entrada, 1) = YEARWEEK(CURDATE(), 1)
                     GROUP BY p.id
                     ORDER BY h DESC");
$hpp = $stmt->fetchAll();
$labels_proj = json_encode(array_column($hpp, 'nom'));
$valors_proj = json_encode(array_map('floatval', array_column($hpp, 'h')));
$colors_proj = json_encode(array_column($hpp, 'color'));

// Empleats treballant ara
$stmt = $pdo->query("SELECT r.id, u.id as uid, u.nom, r.entrada, p.nom as projecte, p.color as color_proj
                     FROM registres r
                     JOIN usuaris u ON r.usuari_id = u.id
                     JOIN projectes p ON r.projecte_id = p.id
                     WHERE r.sortida IS NULL
                     ORDER BY r.entrada ASC");
$treballant_ara_list = $stmt->fetchAll();

// Alertes recents
$stmt = $pdo->query("SELECT a.*, u.nom FROM alertes a
                     JOIN usuaris u ON a.usuari_id = u.id
                     WHERE a.resolta = 0
                     ORDER BY a.data_alerta DESC LIMIT 5");
$alertes_recents = $stmt->fetchAll();

// Projectes en risc (subconsulta per evitar l'error d'àlies al HAVING)
$stmt = $pdo->query("SELECT p.nom, p.color, p.hores_pressupostades, h.hores_reals
                     FROM projectes p
                     LEFT JOIN (
                         SELECT projecte_id,
                                COALESCE(SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600, 0) as hores_reals
                         FROM registres
                         GROUP BY projecte_id
                     ) h ON p.id = h.projecte_id
                     WHERE p.actiu = 1
                       AND (h.hores_reals IS NOT NULL AND h.hores_reals > p.hores_pressupostades)
                     ORDER BY (h.hores_reals - p.hores_pressupostades) DESC");
$projectes_risc = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1>📊 Panell General</h1>
        <p>Visió global de l'activitat de l'empresa en temps real</p>
    </div>
    <div class="text-muted"><?php echo strftime('%A, %d de %B de %Y'); ?></div>
</div>

<div class="kpi-grid">
    <div class="kpi-card info">
        <div class="kpi-icon">⏱️</div>
        <div class="kpi-label">Hores avui</div>
        <div class="kpi-value"><?php echo format_hores($hores_avui); ?></div>
        <div class="kpi-sub">Tots els empleats</div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-icon">👥</div>
        <div class="kpi-label">Treballant ara</div>
        <div class="kpi-value"><?php echo $treballant_ara; ?></div>
        <div class="kpi-sub">de <?php echo $total_empleats; ?> empleats</div>
    </div>
    <div class="kpi-card purple">
        <div class="kpi-icon">📁</div>
        <div class="kpi-label">Projectes actius</div>
        <div class="kpi-value"><?php echo $total_projectes; ?></div>
        <div class="kpi-sub">En curs</div>
    </div>
    <div class="kpi-card <?php echo $alertes_obertes > 0 ? 'danger' : 'success'; ?>">
        <div class="kpi-icon">🚨</div>
        <div class="kpi-label">Alertes obertes</div>
        <div class="kpi-value"><?php echo $alertes_obertes; ?></div>
        <div class="kpi-sub"><?php echo $alertes_obertes > 0 ? 'Revisa-les' : 'Tot correcte'; ?></div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-icon">📆</div>
        <div class="kpi-label">Hores setmana</div>
        <div class="kpi-value"><?php echo format_hores_total($hores_setmana_total); ?></div>
        <div class="kpi-sub">Plantilla completa</div>
    </div>
</div>

<?php if (!empty($projectes_risc)): ?>
<div class="alert alert-danger">
    <span>
        🚨 <strong>ATENCIÓ:</strong> <?php echo count($projectes_risc); ?> projecte(s) han sobrepassat les hores pressupostades.
        <a href="projectes.php" style="color:inherit; text-decoration:underline;">Veure detall →</a>
    </span>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <h3>📈 Hores treballades - últims 7 dies</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="chartDies"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📁 Hores per projecte (aquesta setmana)</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="chartProjectes"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2 mt-3">
    <div class="card">
        <div class="card-header">
            <h3>👥 Treballant ara mateix</h3>
            <span class="badge badge-success"><?php echo $treballant_ara; ?> actius</span>
        </div>
        <div class="card-body no-pad">
            <?php if (empty($treballant_ara_list)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">😴</div>
                    <h3>Cap empleat treballant</h3>
                    <p>No hi ha ningú fitxant ara mateix</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Empleat</th>
                                <th>Projecte</th>
                                <th>Entrada</th>
                                <th>Durada</th>
                                <th>Acció</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($treballant_ara_list as $t):
                                $ent = strtotime($t['entrada']);
                                $dur = time() - $ent;
                            ?>
                            <tr>
                                <td>
                                    <div class="employee-row">
                                        <div class="avatar"><?php echo strtoupper(substr($t['nom'], 0, 1)); ?></div>
                                        <div>
                                            <div class="employee-name"><?php echo e($t['nom']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-dot" style="background:<?php echo e($t['color_proj']); ?>;"></span>
                                    <?php echo e($t['projecte']); ?>
                                </td>
                                <td><?php echo date('H:i', $ent); ?></td>
                                <td><?php echo format_durada($dur); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Tancar el registre d\\'aquest empleat?')">
                                        <input type="hidden" name="tancar_registre" value="1">
                                        <input type="hidden" name="registre_id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🔒 Tancar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🔔 Alertes recents</h3>
            <a href="alertes.php" class="btn btn-ghost btn-sm">Veure totes</a>
        </div>
        <div class="card-body no-pad">
            <?php if (empty($alertes_recents)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <h3>Tot correcte</h3>
                    <p>No hi ha alertes pendents</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tipus</th>
                                <th>Empleat</th>
                                <th>Missatge</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($alertes_recents as $a):
                                $badge_class = ['retard'=>'warning','sortida_anticipada'=>'warning','hores_insuficients'=>'danger','no_fitxa'=>'danger','registre_obert'=>'info'][$a['tipus']] ?? 'gray';
                            ?>
                            <tr>
                                <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo e($a['tipus']); ?></span></td>
                                <td><?php echo e($a['nom']); ?></td>
                                <td class="text-muted"><?php echo e($a['missatge']); ?></td>
                                <td><?php echo date('d/m H:i', strtotime($a['data_alerta'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($projectes_risc)): ?>
<div class="card mt-3">
    <div class="card-header">
        <h3>💸 Projectes en pèrdues</h3>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Projecte</th>
                        <th>Pressupost</th>
                        <th>Consumit</th>
                        <th>Excés</th>
                        <th>Estat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($projectes_risc as $p):
                        $exces = $p['hores_reals'] - $p['hores_pressupostades'];
                        $percent = round(($p['hores_reals'] / $p['hores_pressupostades']) * 100, 0);
                    ?>
                    <tr>
                        <td>
                            <span class="badge-dot" style="background:<?php echo e($p['color']); ?>;"></span>
                            <strong><?php echo e($p['nom']); ?></strong>
                        </td>
                        <td><?php echo number_format($p['hores_pressupostades'], 0); ?>h</td>
                        <td><?php echo number_format($p['hores_reals'], 1); ?>h</td>
                        <td class="text-danger text-bold">+<?php echo number_format($exces, 1); ?>h</td>
                        <td>
                            <div class="progress" style="width:120px;">
                                <div class="progress-bar danger" style="width:<?php echo min($percent, 100); ?>%;"></div>
                            </div>
                            <small class="text-muted"><?php echo $percent; ?>%</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Gràfic de barres: hores per dia
new Chart(document.getElementById('chartDies'), {
    type: 'bar',
    data: {
        labels: [<?php echo implode(',', $labels_dies); ?>],
        datasets: [{
            label: 'Hores',
            data: [<?php echo implode(',', $valors_dies); ?>],
            backgroundColor: 'rgba(37, 99, 235, 0.8)',
            borderColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Hores' } }
        }
    }
});

// Gràfic donut: hores per projecte
new Chart(document.getElementById('chartProjectes'), {
    type: 'doughnut',
    data: {
        labels: <?php echo $labels_proj; ?>,
        datasets: [{
            data: <?php echo $valors_proj; ?>,
            backgroundColor: <?php echo $colors_proj; ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
