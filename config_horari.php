<?php
$page_title = 'Configuració';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $h_entrada = $_POST['hora_entrada_prevista'] ?? '09:00';
    $h_sortida = $_POST['hora_sortida_prevista'] ?? '18:00';
    $h_diaries = (float)($_POST['hores_diaries'] ?? 8);
    $tolerancia = (int)($_POST['tolerancia_minuts'] ?? 15);

    $stmt = $pdo->prepare("UPDATE horari_config SET hora_entrada_prevista=:he, hora_sortida_prevista=:hs, hores_diaries=:hd, tolerancia_minuts=:tol WHERE id=1");
    $stmt->execute(['he' => $h_entrada, 'hs' => $h_sortida, 'hd' => $h_diaries, 'tol' => $tolerancia]);
    set_flash('success', 'Configuració actualitzada correctament.');
    header("Location: config_horari.php");
    exit;
}

$flash = check_flash();
$horari = get_horari_config();
?>

<div class="page-header">
    <div>
        <h1>⚙️ Configuració del sistema</h1>
        <p>Defineix l'horari laboral i les regles de control</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <span><?php echo e($flash['msg']); ?></span>
        <button type="button" class="alert-close" data-dismiss>×</button>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card">
        <div class="card-header">
            <h3>🕐 Horari laboral previst</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>🟢 Hora d'entrada</label>
                        <input type="time" name="hora_entrada_prevista" class="form-control" value="<?php echo e($horari['hora_entrada_prevista']); ?>" required>
                        <small class="text-muted">A partir de la qual s'espera que els empleats entrin</small>
                    </div>
                    <div class="form-group">
                        <label>🔴 Hora de sortida</label>
                        <input type="time" name="hora_sortida_prevista" class="form-control" value="<?php echo e($horari['hora_sortida_prevista']); ?>" required>
                        <small class="text-muted">Hora oficial de sortida</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>⏱️ Hores diaries previstes</label>
                    <input type="number" step="0.5" name="hores_diaries" class="form-control" value="<?php echo e($horari['hores_diaries']); ?>" required>
                    <small class="text-muted">Hores que ha de treballar cada empleat al dia</small>
                </div>
                <div class="form-group">
                    <label>⏳ Tolerància (minuts)</label>
                    <input type="number" name="tolerancia_minuts" class="form-control" value="<?php echo e($horari['tolerancia_minuts']); ?>" required>
                    <small class="text-muted">Minuts de marge abans de considerar retard</small>
                </div>
                <button type="submit" class="btn btn-primary btn-block">💾 Desar configuració</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📊 Resum actual</h3>
        </div>
        <div class="card-body">
            <div style="font-size: 16px; line-height: 2.2;">
                <div class="flex-between">
                    <span>🟢 Entrada:</span>
                    <strong><?php echo substr($horari['hora_entrada_prevista'], 0, 5); ?></strong>
                </div>
                <div class="flex-between">
                    <span>🔴 Sortida:</span>
                    <strong><?php echo substr($horari['hora_sortida_prevista'], 0, 5); ?></strong>
                </div>
                <div class="flex-between">
                    <span>⏱️ Hores/dia:</span>
                    <strong><?php echo $horari['hores_diaries']; ?>h</strong>
                </div>
                <div class="flex-between">
                    <span>⏳ Tolerància:</span>
                    <strong><?php echo $horari['tolerancia_minuts']; ?> min</strong>
                </div>
                <div class="flex-between">
                    <span>📅 Hores/setmana:</span>
                    <strong><?php echo $horari['hores_diaries'] * 5; ?>h</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h3>ℹ️ Informació del sistema</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-3">
            <div>
                <div class="kpi-label">PHP</div>
                <div class="kpi-value" style="font-size:18px;"><?php echo PHP_VERSION; ?></div>
            </div>
            <div>
                <div class="kpi-label">Base de dades</div>
                <div class="kpi-value" style="font-size:18px;">MySQL/MariaDB</div>
            </div>
            <div>
                <div class="kpi-label">Zona horària</div>
                <div class="kpi-value" style="font-size:18px;"><?php echo date_default_timezone_get(); ?></div>
            </div>
            <div>
                <div class="kpi-label">Data actual</div>
                <div class="kpi-value" style="font-size:18px;"><?php echo date('d/m/Y H:i'); ?></div>
            </div>
            <div>
                <div class="kpi-label">Total usuaris</div>
                <div class="kpi-value" style="font-size:18px;"><?php echo $pdo->query("SELECT COUNT(*) FROM usuaris")->fetchColumn(); ?></div>
            </div>
            <div>
                <div class="kpi-label">Total registres</div>
                <div class="kpi-value" style="font-size:18px;"><?php echo $pdo->query("SELECT COUNT(*) FROM registres")->fetchColumn(); ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
