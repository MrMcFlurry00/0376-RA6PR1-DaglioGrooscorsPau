<?php
$page_title = 'Llista Vermella';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

$horari = get_horari_config();
$hores_previstes_diaries = $horari['hores_diaries'];

// Període: avui (per defecte)
$periode = $_GET['periode'] ?? 'avui';
$data_inici = date('Y-m-d');
$data_fi = date('Y-m-d');
$periode_text = 'Avui';

if ($periode === 'setmana') {
    $data_inici = date('Y-m-d', strtotime('monday this week'));
    $data_fi = date('Y-m-d');
    $periode_text = 'Aquesta setmana';
} elseif ($periode === 'mes') {
    $data_inici = date('Y-m-01');
    $data_fi = date('Y-m-d');
    $periode_text = 'Aquest mes';
}

// Consulta: per cada empleat, mirem hores treballades i detecció d'incidències
$stmt = $pdo->prepare("
    SELECT u.id, u.nom, u.email, u.actiu,
           COALESCE(SUM(TIMESTAMPDIFF(SECOND, r.entrada, IFNULL(r.sortida, NOW()))) / 3600, 0) as hores_totals,
           COUNT(r.id) as num_registres,
           (SELECT COUNT(*) FROM registres WHERE usuari_id = u.id AND DATE(entrada) BETWEEN :di AND :df AND sortida IS NULL) as oberts,
           (SELECT MIN(entrada) FROM registres WHERE usuari_id = u.id AND DATE(entrada) BETWEEN :di AND :df) as primera_entrada,
           (SELECT MAX(sortida) FROM registres WHERE usuari_id = u.id AND DATE(entrada) BETWEEN :di AND :df AND sortida IS NOT NULL) as ultima_sortida,
           (SELECT MAX(data_referent) FROM alertes WHERE usuari_id = u.id AND data_referent BETWEEN :di AND :df) as ultima_alerta
    FROM usuaris u
    LEFT JOIN registres r ON u.id = r.usuari_id AND DATE(r.entrada) BETWEEN :di AND :df
    WHERE u.rol = 'empleat' AND u.actiu = 1
    GROUP BY u.id
    ORDER BY hores_totals ASC, u.nom
");
$stmt->execute(['di' => $data_inici, 'df' => $data_fi]);
$empleats = $stmt->fetchAll();

// Calcular hores previstes segons dies laborables del període
$dies_lab = 0;
$cursor = strtotime($data_inici);
$fi = strtotime($data_fi);
while ($cursor <= $fi) {
    $dow = date('N', $cursor);
    if ($dow <= 5) $dies_lab++;
    $cursor = strtotime('+1 day', $cursor);
}
$hores_previstes = $dies_lab * $hores_previstes_diaries;
?>

<div class="page-header">
    <div>
        <h1>🚨 Llista Vermella d'Incompliment</h1>
        <p>Detecció automàtica d'incidències i incompliments horaris</p>
    </div>
</div>

<div class="filter-bar">
    <strong>Període:</strong>
    <a href="?periode=avui" class="btn btn-sm <?php echo $periode === 'avui' ? 'btn-primary' : 'btn-ghost'; ?>">Avui</a>
    <a href="?periode=setmana" class="btn btn-sm <?php echo $periode === 'setmana' ? 'btn-primary' : 'btn-ghost'; ?>">Setmana</a>
    <a href="?periode=mes" class="btn btn-sm <?php echo $periode === 'mes' ? 'btn-primary' : 'btn-ghost'; ?>">Mes</a>
    <span class="text-muted" style="margin-left:auto;">
        Període: <strong><?php echo e($periode_text); ?></strong> · Hores previstes: <strong><?php echo $hores_previstes; ?>h</strong>
    </span>
</div>

<?php
// Classificar empleats
$en_risc = [];
$normals = [];
$destacats = [];
foreach ($empleats as $e) {
    $h = (float)$e['hores_totals'];
    $percent = $hores_previstes > 0 ? ($h / $hores_previstes) * 100 : 0;

    $incidencies = [];
    if ($e['oberts'] > 0) $incidencies[] = "Té {$e['oberts']} registre(s) sense tancar";
    if ($h < $hores_previstes * 0.5 && $hores_previstes > 0) $incidencies[] = 'Hores molt per sota del previst';
    elseif ($h < $hores_previstes * 0.8 && $hores_previstes > 0) $incidencies[] = 'Hores per sota del previst';
    if ($e['primera_entrada']) {
        $primera = strtotime($e['primera_entrada']);
        $prevista = strtotime($horari['hora_entrada_prevista']);
        $tolerancia = $horari['tolerancia_minuts'] * 60;
        if (($primera % 86400) > ($prevista + $tolerancia)) {
            $minuts = round((($primera % 86400) - $prevista) / 60);
            $incidencies[] = "Arribades tardanes (fins {$minuts} min)";
        }
    }
    if ($e['ultima_sortida']) {
        $ultima = strtotime($e['ultima_sortida']);
        $prevista_sortida = strtotime($horari['hora_sortida_prevista']);
        if (($ultima % 86400) < $prevista_sortida - 1800) {
            $incidencies[] = 'Sortides habituals anticipades';
        }
    }

    $e['incidencies'] = $incidencies;
    $e['percent'] = $percent;

    if (!empty($incidencies)) $en_risc[] = $e;
    elseif ($h > $hores_previstes * 1.1) $destacats[] = $e;
    else $normals[] = $e;
}
?>

<div class="kpi-grid">
    <div class="kpi-card danger">
        <div class="kpi-icon">🚨</div>
        <div class="kpi-label">En risc</div>
        <div class="kpi-value"><?php echo count($en_risc); ?></div>
        <div class="kpi-sub">Amb incidències</div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-icon">✅</div>
        <div class="kpi-label">Complint</div>
        <div class="kpi-value"><?php echo count($normals); ?></div>
        <div class="kpi-sub">Dins del previst</div>
    </div>
    <div class="kpi-card info">
        <div class="kpi-icon">🌟</div>
        <div class="kpi-label">Destacats</div>
        <div class="kpi-value"><?php echo count($destacats); ?></div>
        <div class="kpi-sub">Sobre la mitjana</div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-icon">🕳️</div>
        <div class="kpi-label">Registres oberts</div>
        <div class="kpi-value"><?php echo array_sum(array_column($empleats, 'oberts')); ?></div>
        <div class="kpi-sub">Sense tancar</div>
    </div>
</div>

<?php if (!empty($en_risc)): ?>
<div class="card mb-3">
    <div class="card-header" style="background: var(--danger-light);">
        <h3 style="color: var(--danger-dark);">🚨 Empleats amb incidències</h3>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empleat</th>
                        <th>Hores treballades</th>
                        <th>% Previst</th>
                        <th>Registres</th>
                        <th>Incidències detectades</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($en_risc as $e):
                        $percent = $e['percent'];
                        $color = 'danger';
                        if ($percent >= 80) $color = 'warning';
                    ?>
                    <tr>
                        <td>
                            <div class="employee-row">
                                <div class="avatar" style="background:var(--danger-light); color:var(--danger-dark);"><?php echo strtoupper(substr($e['nom'], 0, 1)); ?></div>
                                <div>
                                    <div class="employee-name"><?php echo e($e['nom']); ?></div>
                                    <div class="employee-email"><?php echo e($e['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong class="text-danger"><?php echo format_hores($e['hores_totals']); ?></strong>
                            <small class="text-muted">/ <?php echo $hores_previstes; ?>h</small>
                        </td>
                        <td>
                            <div class="progress" style="width:100px;">
                                <div class="progress-bar <?php echo $color; ?>" style="width:<?php echo min($percent, 100); ?>%;"></div>
                            </div>
                            <small class="text-<?php echo $color; ?>"><?php echo round($percent, 0); ?>%</small>
                        </td>
                        <td>
                            <?php echo (int)$e['num_registres']; ?>
                            <?php if ($e['oberts'] > 0): ?>
                                <span class="badge badge-danger"><?php echo $e['oberts']; ?> obert(s)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach($e['incidencies'] as $inc): ?>
                                <div style="font-size:12px; color: var(--danger-dark); margin: 2px 0;">⚠️ <?php echo e($inc); ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-state-icon">🎉</div>
            <h3>Cap incompliment detectat!</h3>
            <p>Tots els empleats estan complint amb l'horari previst.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($normals) || !empty($destacats)): ?>
<div class="card">
    <div class="card-header">
        <h3>✅ Empleats que compleixen</h3>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empleat</th>
                        <th>Hores treballades</th>
                        <th>% Previst</th>
                        <th>Estat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach(array_merge($destacats, $normals) as $e):
                        $percent = $e['percent'];
                        $color = $percent >= 100 ? 'success' : ($percent >= 80 ? 'warning' : 'danger');
                        $is_destac = in_array($e, $destacats, true);
                    ?>
                    <tr>
                        <td>
                            <div class="employee-row">
                                <div class="avatar"><?php echo strtoupper(substr($e['nom'], 0, 1)); ?></div>
                                <div>
                                    <div class="employee-name"><?php echo e($e['nom']); ?></div>
                                    <div class="employee-email"><?php echo e($e['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo format_hores($e['hores_totals']); ?></strong>
                            <small class="text-muted">/ <?php echo $hores_previstes; ?>h</small>
                        </td>
                        <td>
                            <div class="progress" style="width:100px;">
                                <div class="progress-bar <?php echo $color; ?>" style="width:<?php echo min($percent, 100); ?>%;"></div>
                            </div>
                            <small class="text-<?php echo $color; ?>"><?php echo round($percent, 0); ?>%</small>
                        </td>
                        <td>
                            <?php if ($is_destac): ?>
                                <span class="badge badge-success">🌟 Destacat</span>
                            <?php else: ?>
                                <span class="badge badge-info">Dins del previst</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
