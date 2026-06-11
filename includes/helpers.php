<?php
// Helpers compartits per tota l'aplicació
// Aquest fitxer s'inclou des de header.php i des de fitxar.php,
// per la qual cosa totes les funcions estan disponibles arreu.

// Connexió PDO (s'inclou aquí per si s'usa standalone)
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Obté la configuració de l'horari laboral
function get_horari_config() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM horari_config WHERE id = 1");
    $r = $stmt->fetch();
    return $r ?: [
        'hora_entrada_prevista' => '09:00:00',
        'hora_sortida_prevista' => '18:00:00',
        'hores_diaries' => 8,
        'tolerancia_minuts' => 15
    ];
}

// Hores treballades per un usuari en una data concreta (en hores decimals)
function hores_dia($pdo, $usuari_id, $data) {
    $stmt = $pdo->prepare("SELECT SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) as segons
                           FROM registres WHERE usuari_id = :uid AND DATE(entrada) = :data");
    $stmt->execute(['uid' => $usuari_id, 'data' => $data]);
    $r = $stmt->fetch();
    return ($r && $r['segons']) ? round($r['segons'] / 3600, 2) : 0;
}

// Total d'hores treballades avui per tota la plantilla
function hores_avui_total() {
    global $pdo;
    $stmt = $pdo->query("SELECT SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) as segons
                         FROM registres WHERE DATE(entrada) = CURDATE()");
    $r = $stmt->fetch();
    return ($r && $r['segons']) ? round($r['segons'] / 3600, 1) : 0;
}

// Nombre d'empleats que tenen un registre obert ara mateix
function empleats_treballant() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(DISTINCT usuari_id) as total FROM registres WHERE sortida IS NULL");
    $r = $stmt->fetch();
    return (int)($r['total'] ?? 0);
}

// Formata hores decimals (7.5) com a "7:30"
function format_hores($decimal) {
    if ($decimal === null || $decimal == 0) return '0:00';
    $h = floor($decimal);
    $m = round(($decimal - $h) * 60);
    if ($m == 60) { $h++; $m = 0; }
    return sprintf('%d:%02d', $h, $m);
}

// Formata hores decimals amb hores sempre positives per a sumatoris grans (75.5 -> "75:30")
function format_hores_total($decimal) {
    if ($decimal === null) return '0:00';
    $h = floor($decimal);
    $m = round(($decimal - $h) * 60);
    if ($m == 60) { $h++; $m = 0; }
    return sprintf('%d:%02d', $h, $m);
}

// Tanca un registre obert (acció d'admin)
function tancar_registre($pdo, $registre_id, $sortida_datetime = null, $notes_admin = '') {
    if ($sortida_datetime === null) $sortida_datetime = date('Y-m-d H:i:s');
    $nota_final = '';
    if ($notes_admin) {
        $nota_actual = $pdo->query("SELECT notes FROM registres WHERE id = $registre_id")->fetchColumn();
        $nota_final = ($nota_actual ? $nota_actual . "\n" : '') . '[Tancat per admin] ' . $notes_admin;
    }
    $stmt = $pdo->prepare("UPDATE registres SET sortida = :s" . ($nota_final ? ", notes = :n" : "") . " WHERE id = :id");
    $params = ['s' => $sortida_datetime, 'id' => $registre_id];
    if ($nota_final) $params['n'] = $nota_final;
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function format_durada($segons) {
    if (!$segons || $segons <= 0) return '—';
    $h = floor($segons / 3600);
    $m = floor(($segons % 3600) / 60);
    $s = $segons % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}

function calcular_hores_reals($pdo, $condicio = "1=1") {
    $stmt = $pdo->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, entrada, IFNULL(sortida, NOW()))) / 3600, 0) as h
                         FROM registres WHERE $condicio");
    $r = $stmt->fetch();
    return (float)($r['h'] ?? 0);
}

function crear_alerta($pdo, $usuari_id, $tipus, $missatge, $data_ref = null) {
    $stmt = $pdo->prepare("INSERT INTO alertes (usuari_id, tipus, missatge, data_referent)
                          VALUES (:uid, :tipus, :miss, :data)");
    $stmt->execute([
        'uid' => $usuari_id,
        'tipus' => $tipus,
        'miss' => $missatge,
        'data' => $data_ref ?? date('Y-m-d')
    ]);
}

function check_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function set_flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
