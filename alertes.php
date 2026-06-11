<?php
$page_title = 'Alertes';
require_once 'includes/header.php';
require_once 'includes/helpers.php';

$filtre = $_GET['filtre'] ?? 'totes';

// Accions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['marcar_resolta'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE alertes SET resolta = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        set_flash('success', 'Alerta marcada com a resolta.');
    }
    elseif (isset($_POST['resoldre_totes'])) {
        $pdo->exec("UPDATE alertes SET resolta = 1 WHERE resolta = 0");
        set_flash('success', 'Totes les alertes marcades com a resoltes.');
    }
    elseif (isset($_POST['generar_alertes'])) {
        // Generar alertes automàtiques
        $horari = get_horari_config();
        $h_previstes = $horari['hores_diaries'];

        $stmt = $pdo->query("SELECT u.id, u.nom FROM usuaris u WHERE u.rol='empleat' AND u.actiu=1");
        $empleats = $stmt->fetchAll();
        $creades = 0;

        foreach ($empleats as $e) {
            $h_avui = hores_dia($pdo, $e['id'], date('Y-m-d'));
            if ($h_avui > 0 && $h_avui < $h_previstes * 0.5) {
                // Comprovar que no existeix ja
                $check = $pdo->prepare("SELECT id FROM alertes WHERE usuari_id=:uid AND tipus='hores_insuficients' AND data_referent=CURDATE()");
                $check->execute(['uid' => $e['id']]);
                if (!$check->fetch()) {
                    crear_alerta($pdo, $e['id'], 'hores_insuficients',
                        "Només {$h_avui}h treballades avui (previst: {$h_previstes}h)");
                    $creades++;
                }
            }
            // Comprovar registre obert tot el dia
            $stmt2 = $pdo->prepare("SELECT id, entrada FROM registres WHERE usuari_id=:uid AND sortida IS NULL");
            $stmt2->execute(['uid' => $e['id']]);
            $obert = $stmt2->fetch();
            if ($obert && (time() - strtotime($obert['entrada'])) > 12 * 3600) {
                $check = $pdo->prepare("SELECT id FROM alertes WHERE usuari_id=:uid AND tipus='registre_obert' AND data_referent=DATE(:e)");
                $check->execute(['uid' => $e['id'], 'e' => $obert['entrada']]);
                if (!$check->fetch()) {
                    crear_alerta($pdo, $e['id'], 'registre_obert',
                        "Té un registre obert des de fa més de 12h", date('Y-m-d', strtotime($obert['entrada'])));
                    $creades++;
                }
            }
        }
        set_flash('success', "S'han generat $creades alertes noves.");
    }
    header("Location: alertes.php" . ($filtre ? "?filtre=$filtre" : ''));
    exit;
}

$flash = check_flash();

// Comptar per filtre
$stmt = $pdo->query("SELECT
    COUNT(*) as totes,
    SUM(CASE WHEN resolta=0 THEN 1 ELSE 0 END) as obertes,
    SUM(CASE WHEN resolta=1 THEN 1 ELSE 0 END) as resoltes
    FROM alertes");
$comptes = $stmt->fetch();

// Construir query segons filtre
$where = "1=1";
if ($filtre === 'obertes') $where = "a.resolta = 0";
elseif ($filtre === 'resoltes') $where = "a.resolta = 1";

$stmt = $pdo->query("SELECT a.*, u.nom, u.email
                     FROM alertes a
                     JOIN usuaris u ON a.usuari_id = u.id
                     WHERE $where
                     ORDER BY a.resolta ASC, a.data_alerta DESC
                     LIMIT 200");
$alertes = $stmt->fetchAll();
?>

<div class="page-header">
    <div>
        <h1>🔔 Centre d'Alertes</h1>
        <p>Notificacions automàtiques d'incidències del sistema</p>
    </div>
    <div class="flex gap-1">
        <form method="POST" style="display:inline;">
            <button type="submit" name="generar_alertes" class="btn btn-secondary">
                🔍 Analitzar ara
            </button>
        </form>
        <?php if ($comptes['obertes'] > 0): ?>
        <form method="POST" style="display:inline;">
            <button type="submit" name="resoldre_totes" class="btn btn-success" onclick="return confirm('Marcar TOTES les alertes com a resoltes?')">
                ✓ Resoldre totes
            </button>
        </form>
        <?php endif; ?>
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
        <div class="kpi-icon">🔔</div>
        <div class="kpi-label">Total</div>
        <div class="kpi-value"><?php echo (int)$comptes['totes']; ?></div>
    </div>
    <div class="kpi-card danger">
        <div class="kpi-icon">🚨</div>
        <div class="kpi-label">Obertes</div>
        <div class="kpi-value"><?php echo (int)$comptes['obertes']; ?></div>
    </div>
    <div class="kpi-card success">
        <div class="kpi-icon">✅</div>
        <div class="kpi-label">Resoltes</div>
        <div class="kpi-value"><?php echo (int)$comptes['resoltes']; ?></div>
    </div>
</div>

<div class="filter-bar">
    <a href="?filtre=totes" class="btn btn-sm <?php echo $filtre === 'totes' ? 'btn-primary' : 'btn-ghost'; ?>">Totes</a>
    <a href="?filtre=obertes" class="btn btn-sm <?php echo $filtre === 'obertes' ? 'btn-primary' : 'btn-ghost'; ?>">Obertes</a>
    <a href="?filtre=resoltes" class="btn btn-sm <?php echo $filtre === 'resoltes' ? 'btn-primary' : 'btn-ghost'; ?>">Resoltes</a>
</div>

<div class="card">
    <div class="card-body no-pad">
        <?php if (empty($alertes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎉</div>
                <h3>No hi ha alertes</h3>
                <p>Tot funciona correctament</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Estat</th>
                        <th>Tipus</th>
                        <th>Empleat</th>
                        <th>Missatge</th>
                        <th>Data</th>
                        <th>Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($alertes as $a):
                        $badge_class = ['retard'=>'warning','sortida_anticipada'=>'warning','hores_insuficients'=>'danger','no_fitxa'=>'danger','registre_obert'=>'info'][$a['tipus']] ?? 'gray';
                    ?>
                    <tr style="<?php echo !$a['resolta'] ? 'background: rgba(239, 68, 68, 0.03);' : 'opacity: 0.6;'; ?>">
                        <td>
                            <?php if ($a['resolta']): ?>
                                <span class="badge badge-success">✓ Resolta</span>
                            <?php else: ?>
                                <span class="badge badge-danger">⚠ Oberta</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?php echo $badge_class; ?>"><?php echo e($a['tipus']); ?></span></td>
                        <td>
                            <div class="employee-row">
                                <div class="avatar"><?php echo strtoupper(substr($a['nom'], 0, 1)); ?></div>
                                <div>
                                    <div class="employee-name"><?php echo e($a['nom']); ?></div>
                                    <div class="employee-email"><?php echo e($a['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo e($a['missatge']); ?></td>
                        <td class="text-muted"><?php echo date('d/m/Y H:i', strtotime($a['data_alerta'])); ?></td>
                        <td>
                            <?php if (!$a['resolta']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                <input type="hidden" name="marcar_resolta" value="1">
                                <button class="btn btn-success btn-sm" type="submit">✓</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
