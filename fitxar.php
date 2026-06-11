<?php
session_start();
require_once 'config/database.php';
require_once 'includes/helpers.php';
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$usuari_id = $_SESSION['user_id'];
$page_title = 'Fitxar';

// Comprovar si té un registre actiu sense tancar
$stmt = $pdo->prepare("SELECT r.*, p.nom as projecte_nom, p.color as projecte_color
                       FROM registres r
                       JOIN projectes p ON r.projecte_id = p.id
                       WHERE r.usuari_id = :uid AND r.sortida IS NULL
                       ORDER BY r.entrada DESC LIMIT 1");
$stmt->execute(['uid' => $usuari_id]);
$registre_actiu = $stmt->fetch();

// Processar accions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['entrada']) && !$registre_actiu) {
        $projecte_id = (int)$_POST['projecte_id'];
        $notes = trim($_POST['notes'] ?? '');

        // Comprovar que el projecte existeix i està actiu
        $check = $pdo->prepare("SELECT id, nom FROM projectes WHERE id = :id AND actiu = 1");
        $check->execute(['id' => $projecte_id]);
        if (!$check->fetch()) {
            set_flash('danger', 'Projecte no vàlid.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO registres (usuari_id, projecte_id, entrada, notes) VALUES (:uid, :pid, NOW(), :notes)");
            $stmt->execute(['uid' => $usuari_id, 'pid' => $projecte_id, 'notes' => $notes]);

            // Comprovar retard
            $horari = get_horari_config();
            $entrada_prevista = $horari['hora_entrada_prevista'];
            $tolerancia = $horari['tolerancia_minuts'];
            $hora_actual = date('H:i:s');
            $ts_actual = strtotime($hora_actual);
            $ts_previst = strtotime($entrada_prevista) + ($tolerancia * 60);

            if ($ts_actual > $ts_previst) {
                $minuts_tard = round(($ts_actual - strtotime($entrada_prevista)) / 60);
                crear_alerta($pdo, $usuari_id, 'retard',
                    "Arribada tard: {$minuts_tard} minuts de retard", date('Y-m-d'));
            }

            set_flash('success', 'Entrada registrada correctament. Bon treball! 💪');
        }
    } elseif (isset($_POST['sortida']) && $registre_actiu) {
        $notes = trim($_POST['notes'] ?? '');
        $stmt = $pdo->prepare("UPDATE registres SET sortida = NOW(), notes = CONCAT(IFNULL(notes,''), :notes) WHERE id = :rid");
        $nota_final = $notes ? "\n[Sortida] " . $notes : '';
        $stmt->execute(['rid' => $registre_actiu['id'], 'notes' => $nota_final]);

        // Comprovar sortida anticipada
        $horari = get_horari_config();
        $sortida_prevista = $horari['hora_sortida_prevista'];
        $hora_actual = date('H:i:s');
        $ts_actual = strtotime($hora_actual);
        $ts_previst = strtotime($sortida_prevista);

        if ($ts_actual < $ts_previst) {
            $minuts_avi = round(($ts_previst - $ts_actual) / 60);
            crear_alerta($pdo, $usuari_id, 'sortida_anticipada',
                "Sortida anticipada: {$minuts_avi} minuts abans", date('Y-m-d'));
        }

        set_flash('success', 'Sortida registrada. Fins demà! 👋');
    }
    header("Location: fitxar.php");
    exit;
}

$projectes = $pdo->query("SELECT * FROM projectes WHERE actiu = 1 ORDER BY nom")->fetchAll();

// Hores avui
$hores_avui = hores_dia($pdo, $usuari_id, date('Y-m-d'));

// Hores setmana actual
$hores_setmana = $pdo->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600, 0) as h
                                FROM registres
                                WHERE usuari_id = :uid AND YEARWEEK(entrada, 1) = YEARWEEK(CURDATE(), 1)");
$hores_setmana->execute(['uid' => $usuari_id]);
$hores_setmana = (float)$hores_setmana->fetch()['h'];

// Hores del mes
$hores_mes = $pdo->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600, 0) as h
                            FROM registres
                            WHERE usuari_id = :uid AND MONTH(entrada) = MONTH(CURDATE()) AND YEAR(entrada) = YEAR(CURDATE())");
$hores_mes->execute(['uid' => $usuari_id]);
$hores_mes = (float)$hores_mes->fetch()['h'];

// Últims registres
$ultims = $pdo->prepare("SELECT r.*, p.nom as projecte_nom, p.color as projecte_color
                         FROM registres r
                         JOIN projectes p ON r.projecte_id = p.id
                         WHERE r.usuari_id = :uid
                         ORDER BY r.entrada DESC LIMIT 5");
$ultims->execute(['uid' => $usuari_id]);
$ultims = $ultims->fetchAll();

// Hores per projecte (aquest mes)
$hores_projectes = $pdo->prepare("SELECT p.nom, p.color, COALESCE(SUM(TIMESTAMPDIFF(SECOND, r.entrada, IFNULL(r.sortida, NOW()))) / 3600, 0) as h
                                 FROM registres r
                                 JOIN projectes p ON r.projecte_id = p.id
                                 WHERE r.usuari_id = :uid
                                   AND MONTH(r.entrada) = MONTH(CURDATE())
                                   AND YEAR(r.entrada) = YEAR(CURDATE())
                                 GROUP BY p.id, p.nom, p.color
                                 ORDER BY h DESC");
$hores_projectes->execute(['uid' => $usuari_id]);
$hores_projectes = $hores_projectes->fetchAll();

$flash = check_flash();
$horari = get_horari_config();
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitxar - Control Horari</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fitxar-container { max-width: 600px; margin: 0 auto; }
        .fitxar-card { background: white; border-radius: var(--radius-xl); padding: 28px; box-shadow: var(--shadow); margin-bottom: 20px; }
        .treballant-info { background: var(--primary-50); border-left: 4px solid var(--primary); padding: 16px 18px; border-radius: var(--radius); margin-bottom: 18px; }
        .treballant-info strong { color: var(--primary-dark); }
        .projecte-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 16px; font-size: 13px; font-weight: 600; }
        .timer-display { font-size: 36px; font-weight: 700; color: var(--gray-900); font-variant-numeric: tabular-nums; text-align: center; margin: 16px 0; }
    </style>
</head>
<body class="employee-layout">
    <header class="topbar">
        <div class="topbar-left">
            <div class="logo">⏱️</div>
            <strong>Tracking</strong>
        </div>
        <div class="topbar-right">
            <span class="user-greeting">Hola, <strong><?php echo e($_SESSION['user_nom']); ?></strong></span>
            <a href="logout.php" class="btn btn-ghost btn-sm">Tancar sessió</a>
        </div>
    </header>

    <div class="page-container fitxar-container">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <span><?php echo e($flash['msg']); ?></span>
                <button type="button" class="alert-close" data-dismiss>×</button>
            </div>
        <?php endif; ?>

        <!-- Rellotge gran -->
        <div class="clock-widget">
            <div class="clock-time" id="clock"><?php echo date('H:i:s'); ?></div>
            <div class="clock-date"><?php echo strftime('%A, %d de %B de %Y', strtotime('today')); ?></div>
            <div class="clock-status">
                <?php if ($registre_actiu): ?>
                    <div class="pulse"></div>
                    <span>Treballant des de les <?php echo date('H:i', strtotime($registre_actiu['entrada'])); ?></span>
                <?php else: ?>
                    <span>No has fitxat l'entrada</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- KPIs ràpids -->
        <div class="kpi-grid">
            <div class="kpi-card info">
                <div class="kpi-icon">📅</div>
                <div class="kpi-label">Avui</div>
                <div class="kpi-value"><?php echo format_hores($hores_avui); ?></div>
            </div>
            <div class="kpi-card success">
                <div class="kpi-icon">📆</div>
                <div class="kpi-label">Aquesta setmana</div>
                <div class="kpi-value"><?php echo format_hores($hores_setmana); ?></div>
            </div>
            <div class="kpi-card purple">
                <div class="kpi-icon">📊</div>
                <div class="kpi-label">Aquest mes</div>
                <div class="kpi-value"><?php echo format_hores($hores_mes); ?></div>
            </div>
        </div>

        <!-- Acció principal de fitxar -->
        <div class="fitxar-card">
            <?php if ($registre_actiu): ?>
                <h2 style="margin-top:0; text-align:center;">⏰ Estàs treballant</h2>
                <div class="treballant-info">
                    <strong>Projecte:</strong>
                    <span class="projecte-pill" style="background:<?php echo e($registre_actiu['projecte_color']); ?>20; color:<?php echo e($registre_actiu['projecte_color']); ?>;">
                        <span class="badge-dot" style="background:<?php echo e($registre_actiu['projecte_color']); ?>;"></span>
                        <?php echo e($registre_actiu['projecte_nom']); ?>
                    </span>
                    <br>
                    <strong>Entrada:</strong> <?php echo date('H:i', strtotime($registre_actiu['entrada'])); ?> &nbsp;·&nbsp;
                    <strong>Durada actual:</strong> <span id="timer">0h 00'</span>
                </div>

                <div class="timer-display" id="timerBig">00:00:00</div>

                <form method="POST" id="formSortida">
                    <div class="form-group">
                        <label for="notes_sortida">📝 Què has fet? (opcional)</label>
                        <textarea id="notes_sortida" name="notes" class="form-control" rows="3" placeholder="Resum de la feina feta..."></textarea>
                    </div>
                    <button type="submit" name="sortida" class="btn-fitxar-sortida" onclick="return confirm('Segur que vols fitxar la sortida?')">
                        🛑 FITXAR SORTIDA
                    </button>
                </form>
            <?php else: ?>
                <h2 style="margin-top:0; text-align:center;">👋 Benvingut/da!</h2>
                <p class="text-muted text-center">Selecciona el projecte en què treballaràs i clica el botó per fitxar l'entrada.</p>

                <form method="POST" id="formEntrada">
                    <div class="form-group">
                        <label for="projecte_id">📁 Projecte</label>
                        <select id="projecte_id" name="projecte_id" class="form-control" required>
                            <option value="">-- Selecciona un projecte --</option>
                            <?php foreach($projectes as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo e($p['nom']); ?><?php echo $p['client'] ? ' ('.e($p['client']).')' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">📝 Què faràs avui? (opcional)</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Descripció de la feina prevista..."></textarea>
                    </div>
                    <button type="submit" name="entrada" class="btn-fitxar-entrada">
                        ▶️ FITXAR ENTRADA
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Hores per projecte del mes -->
        <?php if (!empty($hores_projectes)): ?>
        <div class="card">
            <div class="card-header">
                <h3>📊 Hores per projecte (aquest mes)</h3>
            </div>
            <div class="card-body">
                <?php
                $total_mes = array_sum(array_column($hores_projectes, 'h'));
                foreach($hores_projectes as $hp):
                    $percentatge = $total_mes > 0 ? ($hp['h'] / $total_mes) * 100 : 0;
                ?>
                <div style="margin-bottom:14px;">
                    <div class="flex-between mb-1">
                        <span style="font-weight:500;">
                            <span class="badge-dot" style="background:<?php echo e($hp['color']); ?>;"></span>
                            <?php echo e($hp['nom']); ?>
                        </span>
                        <span class="text-bold"><?php echo format_hores($hp['h']); ?> <span class="text-muted">(<?php echo number_format($percentatge, 0); ?>%)</span></span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width:<?php echo $percentatge; ?>%; background:<?php echo e($hp['color']); ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Últims registres -->
        <?php if (!empty($ultims)): ?>
        <div class="card">
            <div class="card-header">
                <h3>🕒 Últims registres</h3>
            </div>
            <div class="card-body no-pad">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Projecte</th>
                                <th>Entrada</th>
                                <th>Sortida</th>
                                <th>Hores</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ultims as $u):
                                $entrada = strtotime($u['entrada']);
                                $sortida = $u['sortida'] ? strtotime($u['sortida']) : null;
                                $dur = $sortida ? ($sortida - $entrada) : 0;
                            ?>
                            <tr>
                                <td><?php echo date('d/m', $entrada); ?></td>
                                <td>
                                    <span class="badge-dot" style="background:<?php echo e($u['projecte_color']); ?>;"></span>
                                    <?php echo e($u['projecte_nom']); ?>
                                </td>
                                <td><?php echo date('H:i', $entrada); ?></td>
                                <td><?php echo $sortida ? date('H:i', $sortida) : '<span class="badge badge-success">Treballant</span>'; ?></td>
                                <td><?php echo $dur ? format_durada($dur) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

<script>
// Rellotge en temps real
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('clock').textContent = `${h}:${m}:${s}`;

    <?php if ($registre_actiu): ?>
    const entrada = new Date('<?php echo $registre_actiu['entrada']; ?>');
    const diff = Math.floor((now - entrada) / 1000);
    const hh = Math.floor(diff / 3600);
    const mm = Math.floor((diff % 3600) / 60);
    const ss = diff % 60;
    const fmt = `${String(hh).padStart(2,'0')}:${String(mm).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
    document.getElementById('timerBig').textContent = fmt;
    document.getElementById('timer').textContent = `${hh}h ${String(mm).padStart(2,'0')}'`;
    <?php endif; ?>
}
setInterval(updateClock, 1000);
updateClock();
</script>
</body>
</html>
