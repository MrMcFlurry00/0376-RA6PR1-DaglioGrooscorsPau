<?php
$page_title = 'Projectes';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// === ACCIONS POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_projecte'])) {
        $nom = trim($_POST['nom'] ?? '');
        $client = trim($_POST['client'] ?? '');
        $desc = trim($_POST['descripcio'] ?? '');
        $hores = (int)($_POST['hores_pressupostades'] ?? 0);
        $color = $_POST['color'] ?? '#2563eb';
        $data_inici = $_POST['data_inici'] ?? null;
        $data_fi = $_POST['data_fi'] ?? null;

        if ($nom && $hores > 0) {
            $stmt = $pdo->prepare("INSERT INTO projectes (nom, client, descripcio, hores_pressupostades, color, data_inici, data_fi)
                                   VALUES (:nom, :cli, :desc, :h, :col, :di, :df)");
            $stmt->execute(['nom' => $nom, 'cli' => $client, 'desc' => $desc, 'h' => $hores, 'col' => $color, 'di' => $data_inici ?: null, 'df' => $data_fi ?: null]);
            set_flash('success', 'Projecte creat correctament.');
        } else {
            set_flash('danger', 'El nom i les hores són obligatoris.');
        }
    }
    elseif (isset($_POST['editar_projecte'])) {
        $pid = (int)$_POST['id'];
        $nom = trim($_POST['nom'] ?? '');
        $client = trim($_POST['client'] ?? '');
        $desc = trim($_POST['descripcio'] ?? '');
        $hores = (int)($_POST['hores_pressupostades'] ?? 0);
        $color = $_POST['color'] ?? '#2563eb';

        $stmt = $pdo->prepare("UPDATE projectes SET nom=:nom, client=:cli, descripcio=:desc, hores_pressupostades=:h, color=:col WHERE id=:id");
        $stmt->execute(['nom' => $nom, 'cli' => $client, 'desc' => $desc, 'h' => $hores, 'col' => $color, 'id' => $pid]);
        set_flash('success', 'Projecte actualitzat.');
    }
    elseif (isset($_POST['toggle_actiu'])) {
        $pid = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE projectes SET actiu = NOT actiu WHERE id = :id");
        $stmt->execute(['id' => $pid]);
        set_flash('success', 'Estat del projecte canviat.');
    }
    elseif (isset($_POST['eliminar_projecte'])) {
        $pid = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM projectes WHERE id = :id");
        $stmt->execute(['id' => $pid]);
        set_flash('success', 'Projecte eliminat.');
    }
    header("Location: projectes.php");
    exit;
}

$flash = check_flash();

// === LLISTAT ===
$stmt = $pdo->query("SELECT p.*,
                            COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600
                                      FROM registres WHERE projecte_id = p.id), 0) as hores_reals,
                            (SELECT COUNT(DISTINCT usuari_id) FROM registres WHERE projecte_id = p.id) as num_empleats,
                            (SELECT COUNT(*) FROM registres WHERE projecte_id = p.id AND sortida IS NULL) as treballant_ara
                     FROM projectes p
                     ORDER BY p.actiu DESC, p.nom");
$projectes = $stmt->fetchAll();

// Estadístiques globals
$total_hores_pressupost = array_sum(array_column($projectes, 'hores_pressupostades'));
$total_hores_reals = array_sum(array_column($projectes, 'hores_reals'));
$projectes_en_risc = count(array_filter($projectes, fn($p) => $p['hores_reals'] > $p['hores_pressupostades']));
?>

<div class="page-header">
    <div>
        <h1>📁 Projectes</h1>
        <p>Gestió i control de tots els projectes de l'empresa</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalCrear').classList.add('show')">
        ➕ Nou projecte
    </button>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <span><?php echo e($flash['msg']); ?></span>
        <button type="button" class="alert-close" data-dismiss>×</button>
    </div>
<?php endif; ?>

<div class="kpi-grid">
    <div class="kpi-card info">
        <div class="kpi-icon">📁</div>
        <div class="kpi-label">Total projectes</div>
        <div class="kpi-value"><?php echo count($projectes); ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-icon">⏰</div>
        <div class="kpi-label">Hores pressupostades</div>
        <div class="kpi-value"><?php echo format_hores_total($total_hores_pressupost); ?></div>
    </div>
    <div class="kpi-card <?php echo $total_hores_reals > $total_hores_pressupost ? 'danger' : 'purple'; ?>">
        <div class="kpi-icon">📊</div>
        <div class="kpi-label">Hores consumides</div>
        <div class="kpi-value"><?php echo format_hores_total($total_hores_reals); ?></div>
    </div>
    <div class="kpi-card <?php echo $projectes_en_risc > 0 ? 'danger' : 'success'; ?>">
        <div class="kpi-icon">💸</div>
        <div class="kpi-label">En risc</div>
        <div class="kpi-value"><?php echo $projectes_en_risc; ?></div>
        <div class="kpi-sub">Sobrepassen pressupost</div>
    </div>
</div>

<div class="grid grid-3">
<?php foreach($projectes as $p):
    $percent = $p['hores_pressupostades'] > 0 ? min(($p['hores_reals'] / $p['hores_pressupostades']) * 100, 150) : 0;
    $estat = 'success';
    if ($percent >= 100) $estat = 'danger';
    elseif ($percent >= 80) $estat = 'warning';
?>
    <div class="project-card" style="border-left-color:<?php echo e($p['color']); ?>;">
        <div class="flex-between mb-1">
            <div class="project-card-name" style="color:<?php echo e($p['color']); ?>;"><?php echo e($p['nom']); ?></div>
            <div>
                <?php if (!$p['actiu']): ?>
                    <span class="badge badge-gray">Inactiu</span>
                <?php elseif ($p['treballant_ara'] > 0): ?>
                    <span class="badge badge-success"><span class="pulse" style="width:6px;height:6px;"></span> Actiu</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($p['client']): ?>
            <div class="project-card-client">🏢 <?php echo e($p['client']); ?></div>
        <?php endif; ?>

        <div class="project-stats">
            <span>Pressupost:</span>
            <strong><?php echo format_hores($p['hores_pressupostades']); ?></strong>
        </div>
        <div class="project-stats">
            <span>Consumit:</span>
            <strong><?php echo format_hores($p['hores_reals']); ?></strong>
        </div>
        <div class="progress" style="margin: 10px 0;">
            <div class="progress-bar <?php echo $estat; ?>" style="width:<?php echo min($percent, 100); ?>%;"></div>
        </div>
        <div class="flex-between" style="font-size:12px; color:var(--text-muted);">
            <span><?php echo round($percent, 0); ?>% usat</span>
            <span><?php echo $p['num_empleats']; ?> empleats</span>
        </div>

        <?php if ($p['hores_reals'] > $p['hores_pressupostades']): ?>
            <div class="alert alert-danger mt-2" style="margin: 10px 0 0 0; padding: 8px 10px; font-size: 12px;">
                🚨 <strong>Excés:</strong> +<?php echo format_hores($p['hores_reals'] - $p['hores_pressupostades']); ?>
            </div>
        <?php endif; ?>

        <div class="flex gap-1 mt-2" style="margin-top:12px;">
            <button class="btn btn-ghost btn-sm" onclick="editarProjecte(<?php echo htmlspecialchars(json_encode($p)); ?>)" style="flex:1;">
                ✏️ Editar
            </button>
            <form method="POST" style="flex:1; display:inline;">
                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                <input type="hidden" name="toggle_actiu" value="1">
                <button class="btn btn-ghost btn-sm btn-block" type="submit">
                    <?php echo $p['actiu'] ? '⏸️ Desactivar' : '▶️ Activar'; ?>
                </button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if (empty($projectes)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📁</div>
        <h3>No hi ha projectes</h3>
        <p>Crea el primer projecte per començar</p>
    </div>
<?php endif; ?>

<!-- Modal crear -->
<div class="modal-backdrop" id="modalCrear">
    <div class="modal">
        <form method="POST">
            <div class="modal-header">
                <h3>➕ Nou projecte</h3>
                <button type="button" class="alert-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nom del projecte *</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Client</label>
                    <input type="text" name="client" class="form-control">
                </div>
                <div class="form-group">
                    <label>Descripció</label>
                    <textarea name="descripcio" class="form-control" rows="2"></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Hores pressupostades *</label>
                        <input type="number" name="hores_pressupostades" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" class="form-control" value="#2563eb">
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Data inici</label>
                        <input type="date" name="data_inici" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Data fi</label>
                        <input type="date" name="data_fi" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('show')">Cancel·lar</button>
                <button type="submit" name="crear_projecte" class="btn btn-primary">Crear projecte</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal editar -->
<div class="modal-backdrop" id="modalEditar">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h3>✏️ Editar projecte</h3>
                <button type="button" class="alert-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" id="edit_nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Client</label>
                    <input type="text" name="client" id="edit_client" class="form-control">
                </div>
                <div class="form-group">
                    <label>Descripció</label>
                    <textarea name="descripcio" id="edit_desc" class="form-control" rows="2"></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Hores pressupostades *</label>
                        <input type="number" name="hores_pressupostades" id="edit_hores" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" id="edit_color" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('show')">Cancel·lar</button>
                <button type="submit" name="editar_projecte" class="btn btn-primary">Desar canvis</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarProjecte(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_nom').value = p.nom;
    document.getElementById('edit_client').value = p.client || '';
    document.getElementById('edit_desc').value = p.descripcio || '';
    document.getElementById('edit_hores').value = p.hores_pressupostades;
    document.getElementById('edit_color').value = p.color;
    document.getElementById('modalEditar').classList.add('show');
}
</script>

<?php require_once 'includes/footer.php'; ?>
