<?php
$page_title = 'Empleats';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

// Accions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_empleat'])) {
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'empleat';
        $pass = $_POST['contrasenya'] ?? 'password';

        if ($nom && $email) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO usuaris (nom, email, contrasenya, rol) VALUES (:nom, :email, :pass, :rol)");
                $stmt->execute(['nom' => $nom, 'email' => $email, 'pass' => $hash, 'rol' => $rol]);
                set_flash('success', "Empleat creat. Contrasenya inicial: $pass");
            } catch (PDOException $e) {
                set_flash('danger', 'Error: email ja existent.');
            }
        } else {
            set_flash('danger', 'Nom i email obligatoris.');
        }
    }
    elseif (isset($_POST['editar_empleat'])) {
        $uid = (int)$_POST['id'];
        $nom = trim($_POST['nom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? 'empleat';
        $actiu = isset($_POST['actiu']) ? 1 : 0;
        $nova_pass = $_POST['nova_contrasenya'] ?? '';

        $sql = "UPDATE usuaris SET nom=:nom, email=:email, rol=:rol, actiu=:actiu";
        $params = ['nom' => $nom, 'email' => $email, 'rol' => $rol, 'actiu' => $actiu, 'id' => $uid];
        if ($nova_pass) {
            $sql .= ", contrasenya=:pass";
            $params['pass'] = password_hash($nova_pass, PASSWORD_BCRYPT);
        }
        $sql .= " WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        set_flash('success', 'Empleat actualitzat.');
    }
    elseif (isset($_POST['eliminar_empleat'])) {
        $uid = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM usuaris WHERE id = :id AND id != :admin_id");
        $stmt->execute(['id' => $uid, 'admin_id' => $_SESSION['user_id']]);
        set_flash('success', 'Empleat eliminat.');
    }
    header("Location: empleats.php");
    exit;
}

$flash = check_flash();

// Llistat d'empleats amb estadístiques
$stmt = $pdo->query("SELECT u.*,
                            COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600
                                      FROM registres WHERE usuari_id = u.id), 0) as hores_totals,
                            COALESCE((SELECT SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600
                                      FROM registres WHERE usuari_id = u.id
                                      AND YEARWEEK(entrada, 1) = YEARWEEK(CURDATE(), 1)), 0) as hores_setmana,
                            (SELECT id FROM registres WHERE usuari_id = u.id AND sortida IS NULL LIMIT 1) as treballant_id,
                            (SELECT MAX(DATE(entrada)) FROM registres WHERE usuari_id = u.id) as ultim_dia,
                            (SELECT COUNT(*) FROM registres WHERE usuari_id = u.id) as num_registres
                     FROM usuaris u
                     ORDER BY u.actiu DESC, u.nom");
$empleats = $stmt->fetchAll();

$horari = get_horari_config();
?>

<div class="page-header">
    <div>
        <h1>👥 Empleats</h1>
        <p>Gestió de la plantilla de l'empresa</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalCrear').classList.add('show')">
        ➕ Nou empleat
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
        <div class="kpi-icon">👥</div>
        <div class="kpi-label">Total empleats</div>
        <div class="kpi-value"><?php echo count($empleats); ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-icon">✅</div>
        <div class="kpi-label">Actius</div>
        <div class="kpi-value"><?php echo count(array_filter($empleats, fn($e) => $e['actiu'])); ?></div>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-icon">🟢</div>
        <div class="kpi-label">Treballant ara</div>
        <div class="kpi-value"><?php echo count(array_filter($empleats, fn($e) => $e['treballant_id'])); ?></div>
    </div>
    <div class="kpi-card purple">
        <div class="kpi-icon">⏱️</div>
        <div class="kpi-label">Mitjana hores/setmana</div>
        <div class="kpi-value">
            <?php
            $h_set = array_filter(array_column($empleats, 'hores_setmana'));
            $mitjana = count($h_set) > 0 ? array_sum($h_set) / count($h_set) : 0;
            echo format_hores($mitjana);
            ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>📋 Llistat d'empleats</h3>
    </div>
    <div class="card-body no-pad">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empleat</th>
                        <th>Rol</th>
                        <th>Estat</th>
                        <th>Hores setmana</th>
                        <th>Hores totals</th>
                        <th>Última activitat</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($empleats as $emp):
                        $treballant = $emp['treballant_id'];
                        $h_set = $emp['hores_setmana'];
                        $color = 'success';
                        if ($h_set >= $horari['hores_diaries']*5) $color = 'success';
                        elseif ($h_set >= $horari['hores_diaries']*3) $color = 'warning';
                        else $color = 'danger';
                    ?>
                    <tr>
                        <td>
                            <div class="employee-row">
                                <div class="avatar"><?php echo strtoupper(substr($emp['nom'], 0, 1)); ?></div>
                                <div>
                                    <div class="employee-name"><?php echo e($emp['nom']); ?></div>
                                    <div class="employee-email"><?php echo e($emp['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($emp['rol'] === 'admin'): ?>
                                <span class="badge badge-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Empleat</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$emp['actiu']): ?>
                                <span class="badge badge-gray">Inactiu</span>
                            <?php elseif ($treballant): ?>
                                <span class="badge badge-success"><span class="pulse" style="width:6px;height:6px;"></span> Treballant</span>
                            <?php else: ?>
                                <span class="badge badge-info">Disponible</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-<?php echo $color; ?>"><?php echo format_hores($h_set); ?></strong>
                            <div class="progress" style="width:80px; height:6px; margin-top:4px;">
                                <div class="progress-bar <?php echo $color; ?>" style="width:<?php echo min(($h_set / 40) * 100, 100); ?>%;"></div>
                            </div>
                        </td>
                        <td><?php echo format_hores($emp['hores_totals']); ?></td>
                        <td class="text-muted">
                            <?php echo $emp['ultim_dia'] ? date('d/m/Y', strtotime($emp['ultim_dia'])) : '—'; ?>
                        </td>
                        <td>
                            <button class="btn btn-ghost btn-sm" onclick='editarEmpleat(<?php echo json_encode($emp, JSON_HEX_APOS); ?>)'>✏️</button>
                            <?php if ($emp['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar aquest empleat? Tots els seus registres s\\'eliminaran.')">
                                <input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                <input type="hidden" name="eliminar_empleat" value="1">
                                <button class="btn btn-ghost btn-sm" type="submit">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal crear -->
<div class="modal-backdrop" id="modalCrear">
    <div class="modal">
        <form method="POST">
            <div class="modal-header">
                <h3>➕ Nou empleat</h3>
                <button type="button" class="alert-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nom complet *</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol" class="form-control">
                        <option value="empleat">Empleat</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Contrasenya inicial</label>
                    <input type="text" name="contrasenya" class="form-control" value="password">
                    <small class="text-muted">L'empleat la podrà canviar posteriorment.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('show')">Cancel·lar</button>
                <button type="submit" name="crear_empleat" class="btn btn-primary">Crear</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal editar -->
<div class="modal-backdrop" id="modalEditar">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="id" id="emp_id">
            <div class="modal-header">
                <h3>✏️ Editar empleat</h3>
                <button type="button" class="alert-close" onclick="this.closest('.modal-backdrop').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" id="emp_nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="emp_email" class="form-control" required>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="rol" id="emp_rol" class="form-control">
                            <option value="empleat">Empleat</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estat</label>
                        <div style="padding:11px 0;">
                            <label style="font-weight:normal;">
                                <input type="checkbox" name="actiu" id="emp_actiu" checked>
                                Empleat actiu
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nova contrasenya (deixar en blanc per mantenir)</label>
                    <input type="text" name="nova_contrasenya" class="form-control" placeholder="••••••••">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('show')">Cancel·lar</button>
                <button type="submit" name="editar_empleat" class="btn btn-primary">Desar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarEmpleat(e) {
    document.getElementById('emp_id').value = e.id;
    document.getElementById('emp_nom').value = e.nom;
    document.getElementById('emp_email').value = e.email;
    document.getElementById('emp_rol').value = e.rol;
    document.getElementById('emp_actiu').checked = e.actiu == 1;
    document.getElementById('modalEditar').classList.add('show');
}
</script>

<?php require_once 'includes/footer.php'; ?>
