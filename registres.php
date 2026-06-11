<?php
$page_title = 'Registres';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

// Accions: editar, eliminar o tancar registre obert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tancar_registre'])) {
        $rid = (int)$_POST['id'];
        $sortida = $_POST['sortida_dt'] ?? null;
        $notes = trim($_POST['notes_admin'] ?? '');
        if (tancar_registre($pdo, $rid, $sortida ?: null, $notes)) {
            set_flash('success', 'Registre obert tancat correctament.');
        } else {
            set_flash("danger", "No s'ha pogut tancar el registre.");
        }
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'registres.php'));
        exit;
    }
    if (isset($_POST['editar_registre'])) {
        $rid = (int)$_POST['id'];
        $entrada = $_POST['entrada'] ?? '';
        $sortida = $_POST['sortida'] ?? null;
        $projecte_id = (int)$_POST['projecte_id'];

        if ($entrada) {
            $sortida_sql = $sortida ? $sortida : null;
            $stmt = $pdo->prepare("UPDATE registres SET entrada=:e, sortida=:s, projecte_id=:p WHERE id=:id");
            $stmt->execute(['e' => $entrada, 's' => $sortida_sql, 'p' => $projecte_id, 'id' => $rid]);
            set_flash('success', 'Registre actualitzat.');
        }
    }
    elseif (isset($_POST['eliminar_registre'])) {
        $rid = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM registres WHERE id = :id");
        $stmt->execute(['id' => $rid]);
        set_flash('success', 'Registre eliminat.');
    }
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'registres.php');
    exit;
}

$flash = check_flash();

// Filtres
$filtre_usuari = (int)($_GET['usuari'] ?? 0);
$filtre_projecte = (int)($_GET['projecte'] ?? 0);
$filtre_data = $_GET['data'] ?? '';
$filtre_des_de = $_GET['des_de'] ?? '';
$filtre_fins_a = $_GET['fins_a'] ?? '';

$where = [];
$params = [];
if ($filtre_usuari) { $where[] = "r.usuari_id = :uid"; $params['uid'] = $filtre_usuari; }
if ($filtre_projecte) { $where[] = "r.projecte_id = :pid"; $params['pid'] = $filtre_projecte; }
if ($filtre_data) { $where[] = "DATE(r.entrada) = :data"; $params['data'] = $filtre_data; }
if ($filtre_des_de) { $where[] = "DATE(r.entrada) >= :des_de"; $params['des_de'] = $filtre_des_de; }
if ($filtre_fins_a) { $where[] = "DATE(r.entrada) <= :fins_a"; $params['fins_a'] = $filtre_fins_a; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT r.*, u.nom as usuari_nom, u.email, p.nom as projecte_nom, p.color as projecte_color
                       FROM registres r
                       JOIN usuaris u ON r.usuari_id = u.id
                       JOIN projectes p ON r.projecte_id = p.id
                       $where_sql
                       ORDER BY r.entrada DESC
                       LIMIT 300");
$stmt->execute($params);
$registres = $stmt->fetchAll();

// Total hores del filtre
$total_hores = 0;
foreach ($registres as $r) {
    if ($r['sortida']) {
        $total_hores += (strtotime($r['sortida']) - strtotime($r['entrada'])) / 3600;
    }
}

// Dades per als selects
$usuaris = $pdo->query("SELECT id, nom FROM usuaris WHERE actiu=1 ORDER BY nom")->fetchAll();
$projectes = $pdo->query("SELECT id, nom FROM projectes ORDER BY nom")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1>📋 Tots els registres</h1>
        <p>Historial complet d'entrades i sortides</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <span><?php echo e($flash['msg']); ?></span>
        <button type="button" class="alert-close" data-dismiss>×</button>
    </div>
<?php endif; ?>

<div class="kpi-grid">
    <div class="kpi-card info">
        <div class="kpi-icon">📋</div>
        <div class="kpi-label">Total registres</div>
        <div class="kpi-value"><?php echo count($registres); ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-icon">⏱️</div>
        <div class="kpi-label">Hores totals</div>
        <div class="kpi-value"><?php echo format_hores_total($total_hores); ?></div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-icon">🟢</div>
        <div class="kpi-label">Oberts</div>
        <div class="kpi-value"><?php echo count(array_filter($registres, fn($r) => !$r['sortida'])); ?></div>
    </div>
    <div class="kpi-card purple">
        <div class="kpi-icon">📊</div>
        <div class="kpi-label">Mitjana per registre</div>
        <div class="kpi-value">
            <?php
            $tancats = array_filter($registres, fn($r) => $r['sortida']);
            $mitjana = count($tancats) > 0 ? $total_hores / count($tancats) : 0;
            echo format_hores($mitjana);
            ?>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="filter-bar" style="margin:0;">
            <select name="usuari" class="form-control">
                <option value="">Tots els empleats</option>
                <?php foreach($usuaris as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $filtre_usuari == $u['id'] ? 'selected' : ''; ?>>
                        <?php echo e($u['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="projecte" class="form-control">
                <option value="">Tots els projectes</option>
                <?php foreach($projectes as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $filtre_projecte == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo e($p['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="des_de" class="form-control" value="<?php echo e($filtre_des_de); ?>" placeholder="Des de">
            <input type="date" name="fins_a" class="form-control" value="<?php echo e($filtre_fins_a); ?>" placeholder="Fins a">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="registres.php" class="btn btn-ghost">✕ Netejar</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empleat</th>
                        <th>Projecte</th>
                        <th>Data</th>
                        <th>Entrada</th>
                        <th>Sortida</th>
                        <th>Durada</th>
                        <th>Estat</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($registres as $r):
                        $ent = strtotime($r['entrada']);
                        $sort = $r['sortida'] ? strtotime($r['sortida']) : null;
                        $dur = $sort ? ($sort - $ent) : 0;
                    ?>
                    <tr>
                        <td>
                            <div class="employee-row">
                                <div class="avatar"><?php echo strtoupper(substr($r['usuari_nom'], 0, 1)); ?></div>
                                <div class="employee-name"><?php echo e($r['usuari_nom']); ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-dot" style="background:<?php echo e($r['projecte_color']); ?>;"></span>
                            <?php echo e($r['projecte_nom']); ?>
                        </td>
                        <td><?php echo date('d/m/Y', $ent); ?> <small class="text-muted"><?php echo date('D', $ent); ?></small></td>
                        <td><?php echo date('H:i', $ent); ?></td>
                        <td>
                            <?php if ($sort): ?>
                                <?php echo date('H:i', $sort); ?>
                            <?php else: ?>
                                <span class="badge badge-success"><span class="pulse" style="width:6px;height:6px;"></span> Obert</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $dur ? format_durada($dur) : '—'; ?></td>
                        <td>
                            <?php if ($sort): ?>
                                <span class="badge badge-gray">Tancat</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-ghost btn-sm" onclick='editarRegistre(<?php echo json_encode($r, JSON_HEX_APOS); ?>)'>✏️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar aquest registre?')">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <input type="hidden" name="eliminar_registre" value="1">
                                <button class="btn btn-ghost btn-sm" type="submit">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (empty($registres)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <h3>No hi ha registres</h3>
        <p>Cap registre coincideix amb els filtres</p>
    </div>
<?php endif; ?>

<!-- Modal editar registre -->
<div class="modal-backdrop" id="modalEditarReg">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="id" id="reg_id">
            <div class="modal-header">
                <h3>✏️ Editar registre</h3>
                <button type="button" class="alert-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Projecte</label>
                    <select name="projecte_id" id="reg_projecte" class="form-control" required>
                        <?php foreach($projectes as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo e($p['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Entrada</label>
                        <input type="datetime-local" name="entrada" id="reg_entrada" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Sortida (buit = obert)</label>
                        <input type="datetime-local" name="sortida" id="reg_sortida" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('show')">Cancel·lar</button>
                <button type="submit" name="editar_registre" class="btn btn-primary">Desar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarRegistre(r) {
    document.getElementById('reg_id').value = r.id;
    document.getElementById('reg_projecte').value = r.projecte_id;
    document.getElementById('reg_entrada').value = r.entrada.replace(' ', 'T').substring(0, 16);
    document.getElementById('reg_sortida').value = r.sortida ? r.sortida.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('modalEditarReg').classList.add('show');
}
</script>

<?php require_once 'includes/footer.php'; ?>
