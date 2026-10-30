<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
date_default_timezone_set('America/Sao_Paulo');
$CFG = require ROOT . '/config/config.php';
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
require ROOT . '/app/schema.php';

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('sqlite:' . ROOT . '/storage/banco.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys=ON');
        ensure_socials($pdo);
    }
    return $pdo;
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $to): never { header('Location: ' . $to); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_check(): void {
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { http_response_code(413); exit('Os arquivos enviados passaram do limite do servidor (post_max_size = ' . ini_get('post_max_size') . '). Volte, envie menos ou menores arquivos, ou aumente esse limite no php.ini.'); }
    $t = (string)($_SESSION['csrf'] ?? '');
    if ($t === '' || !hash_equals($t, (string)($_POST['csrf'] ?? ''))) { http_response_code(419); exit('Sessão expirada. Volte e tente de novo.'); }
}
function installed(): bool {
    try { return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0; } catch (Throwable $x) { return false; }
}
function setting(string $k, string $d = ''): string {
    static $all;
    if ($all === null) { try { $all = db()->query('SELECT k,v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $x) { $all = []; } }
    return $all[$k] ?? $d;
}
function set_setting(string $k, string $v): void {
    db()->prepare('INSERT INTO settings(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$k, $v]);
}
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if (!is_admin()) redirect('/admin/login'); }
function flash(?string $msg = null, string $type = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}
function unique_slug(string $table, string $base): string {
    $slug = $base = slugify($base); $i = 2;
    $q = db()->prepare("SELECT 1 FROM $table WHERE slug=?");
    while (true) { $q->execute([$slug]); if (!$q->fetchColumn()) return $slug; $slug = $base . '-' . $i++; }
}
function client_hash(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')); }

/** Upload seguro: só JPG/PNG/WebP até 2 MB, recorte quadrado, salvo como PNG com nome aleatório. */
function upload_image(array $f, string $folder, int $px = 300, ?int $py = null): ?string {
    $py ??= $px;
    global $CFG;
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('A imagem passa do limite do servidor (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').');
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > $CFG['max_upload']) throw new RuntimeException('Imagem inválida ou maior que 8 MB.');
    if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('Extensão GD do PHP não está ativa.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png'  => @imagecreatefrompng($f['tmp_name']),
        'image/webp' => @imagecreatefromwebp($f['tmp_name']),
        default      => false,
    };
    if (!$src) throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP.');
    $w = imagesx($src); $h = imagesy($src); $ar = $px / $py;
    if ($w / $h > $ar) { $cw = (int)($h * $ar); $ch = $h; } else { $cw = $w; $ch = (int)($w / $ar); }
    $dst = imagecreatetruecolor($px, $py);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $cw, 2), intdiv($h - $ch, 2), $px, $py, $cw, $ch);
    $dir = ROOT . '/public/uploads/' . $folder;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.png';
    imagepng($dst, "$dir/$name");
    return "/uploads/$folder/$name";
}
