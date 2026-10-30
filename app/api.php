<?php
function yt_id(string $u): ?string { return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $u, $m) ? $m[1] : null; }
function video_html(array $p): string {
    if (($p['video_type'] ?? '') === 'youtube' && ($id = yt_id((string)$p['video_url']))) return '<div id="galVideo" class="vid"><iframe data-src="https://www.youtube-nocookie.com/embed/' . $id . '?rel=0" title="Vídeo do produto" allow="encrypted-media; picture-in-picture" allowfullscreen></iframe></div>';
    if (($p['video_type'] ?? '') === 'upload' && str_starts_with((string)$p['video_url'], '/uploads/videos/')) return '<div id="galVideo" class="vid"><video controls preload="metadata" playsinline src="' . e($p['video_url']) . '"></video></div>';
    return '';
}
function upload_video(array $f): ?string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('O vídeo passa do limite do servidor PHP (upload_max_filesize = ' . ini_get('upload_max_filesize') . '). Aumente esse limite no php.ini (ou na hospedagem) ou use o link do YouTube.');
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 50 * 1024 * 1024) throw new RuntimeException('Vídeo inválido ou maior que 50 MB. Para vídeos grandes, use o link do YouTube.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $ext = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/x-m4v' => 'mp4'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Formato de vídeo não aceito (' . $mime . '). Envie MP4 (H.264) ou WebM.');
    $d = ROOT . '/public/uploads/videos'; if (!is_dir($d)) mkdir($d, 0755, true);
    $n = bin2hex(random_bytes(8)) . '.' . $ext; if (!move_uploaded_file($f['tmp_name'], "$d/$n")) throw new RuntimeException('Não foi possível salvar o vídeo.');
    return '/uploads/videos/' . $n;
}
function drop_video(?string $path): void { if ($path && str_starts_with($path, '/uploads/videos/')) @unlink(ROOT . '/public' . $path); }
function share_dialog(array $p, string $url): string {
    return '<dialog id="mailDlg" class="dlg"><form class="form" id="mailForm">' . csrf_field() . '<h2>Compartilhar por e-mail</h2><input type="hidden" name="url" value="' . e($url) . '"><input type="hidden" name="title" value="' . e($p['title']) . '">'
        . '<input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><label>Seu nome<input name="from_name" required maxlength="60"></label><label>Nome do amigo<input name="to_name" required maxlength="60"></label>'
        . '<label>E-mail do amigo<input type="email" name="to_email" required></label><label>Mensagem<textarea name="message" rows="4" maxlength="500">Achei que você ia gostar deste produto: ' . e($p['title']) . '. Confira todos os detalhes em: ' . e($url) . '</textarea></label>'
        . '<p class="msg-out" role="status"></p><div class="act"><button class="btn ghost" type="button" data-close>Cancelar</button><button class="btn" type="submit">Enviar</button></div></form></dialog>';
}
function json_out(array $d, int $code = 200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function page_share_email(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'msg' => 'Método inválido.'], 405);
    csrf_check(); $h = 'm:' . client_hash(); $n = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND ts>?'); $n->execute([$h, time() - 3600]);
    if (!empty($_POST['website'])) json_out(['ok' => true, 'msg' => 'Enviado!']);
    if ((int)$n->fetchColumn() >= 5) json_out(['ok' => false, 'msg' => 'Muitos envios em pouco tempo. Tente mais tarde.'], 429);
    $clean = fn($s, $l) => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$s)), 0, $l);
    $from = $clean($_POST['from_name'] ?? '', 60); $to = $clean($_POST['to_name'] ?? '', 60); $email = trim((string)($_POST['to_email'] ?? '')); $url = (string)($_POST['url'] ?? '');
    $msg = mb_substr(trim(str_replace("\r", '', (string)($_POST['message'] ?? ''))), 0, 500);
    if ($from === '' || $to === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($msg) < 5 || parse_url($url, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '') && parse_url($url, PHP_URL_HOST) !== explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) json_out(['ok' => false, 'msg' => 'Confira os campos e tente de novo.'], 422);
    db()->prepare('INSERT INTO login_attempts(ip_hash,ts) VALUES(?,?)')->execute([$h, time()]);
    $host = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
    $ok = @mail($email, '=?UTF-8?B?' . base64_encode("$from compartilhou um achadinho com você") . '?=', "Olá, $to!\n\n$msg\n\n— Enviado por $from via " . site_name(), "From: " . site_name() . " <noreply@$host>\r\nContent-Type: text/plain; charset=UTF-8");
    json_out($ok ? ['ok' => true, 'msg' => 'E-mail enviado!'] : ['ok' => false, 'msg' => 'Não foi possível enviar agora. Use o link para compartilhar.'], $ok ? 200 : 502);
}
/* ---------- API REST ---------- */
function api_auth(): void {
    $k = $_SERVER['HTTP_X_API_KEY'] ?? (preg_match('/^Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m) ? $m[1] : '');
    $q = db()->prepare('SELECT 1 FROM api_keys WHERE key_hash=? AND revoked=0'); $q->execute([hash('sha256', (string)$k)]);
    if ($k === '' || !$q->fetch()) json_out(['error' => 'Chave de API inválida ou ausente.'], 401);
}
function api_fetch_image(string $url): string {
    $p = parse_url($url); $host = $p['host'] ?? '';
    if (!in_array($p['scheme'] ?? '', ['http', 'https'], true) || $host === '') throw new RuntimeException('image_url inválida.');
    $ip = gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) throw new RuntimeException('image_url aponta para endereço não permitido.');
    $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8, 'follow_location' => 0, 'max_redirects' => 0, 'user_agent' => 'AchadinhosBot/1.0']]), 0, 3 * 1024 * 1024);
    if (!$data || !($im = @imagecreatefromstring($data))) throw new RuntimeException('Não foi possível ler a imagem de image_url.');
    $w = imagesx($im); $h = imagesy($im); $s = min($w, $h); $dst = imagecreatetruecolor(900, 900);
    imagecopyresampled($dst, $im, 0, 0, intdiv($w - $s, 2), intdiv($h - $s, 2), 900, 900, $s, $s);
    $d = ROOT . '/public/uploads/products'; if (!is_dir($d)) mkdir($d, 0755, true); $n = bin2hex(random_bytes(8)) . '.png'; imagepng($dst, "$d/$n"); return '/uploads/products/' . $n;
}
function api_router(string $path): void {
    api_auth(); $m = $_SERVER['REQUEST_METHOD']; $body = json_decode((string)file_get_contents('php://input'), true);
    if ($path === '/api/v1/stores' && $m === 'GET') json_out(['data' => db()->query('SELECT id,name,slug,active FROM stores ORDER BY sort,name')->fetchAll()]);
    if ($path === '/api/v1/categories' && $m === 'GET') json_out(['data' => db()->query('SELECT id,name,slug,parent_id FROM categories ORDER BY sort,name')->fetchAll()]);
    if (!preg_match('#^/api/v1/products(?:/(\d+))?$#', $path, $mm) || !in_array($m, ['POST', 'PUT'], true) || ($m === 'POST') === isset($mm[1])) json_out(['error' => 'Rota ou método não encontrado.'], 404);
    if (!is_array($body)) json_out(['error' => 'Envie um JSON válido.'], 400);
    $id = isset($mm[1]) ? (int)$mm[1] : null;
    if ($id && !db()->query("SELECT 1 FROM products WHERE id=$id")->fetch()) json_out(['error' => 'Produto não encontrado.'], 404);
    $GLOBALS['api_images'] = []; $_POST = ['title' => $body['title'] ?? '', 'short_desc' => $body['short_desc'] ?? '', 'description' => $body['description'] ?? '', 'keywords' => $body['keywords'] ?? '', 'featured' => !empty($body['featured']) ? 1 : 0, 'status' => $body['status'] ?? 'published', 'sku' => $body['sku'] ?? '', 'cats' => [], 'links' => []];
    try {
        if ($id) { $cur = db()->query("SELECT * FROM products WHERE id=$id")->fetch(); foreach (['title', 'short_desc', 'description', 'keywords', 'sku'] as $k) if (!array_key_exists($k, $body)) $_POST[$k] = $cur[$k]; if (!array_key_exists('featured', $body)) $_POST['featured'] = $cur['featured']; if (!array_key_exists('status', $body)) $_POST['status'] = $cur['status']; }
        foreach ((array)($body['categories'] ?? $body['category'] ?? []) as $c) {
            $q = db()->prepare('SELECT id FROM categories WHERE id=? OR slug=? OR lower(name)=lower(?)'); $q->execute([is_numeric($c) ? (int)$c : 0, slugify((string)$c), (string)$c]);
            if (!($cid = $q->fetchColumn())) { db()->prepare('INSERT INTO categories(name,slug) VALUES(?,?)')->execute([mb_substr((string)$c, 0, 60), unique_slug('categories', (string)$c)]); $cid = db()->lastInsertId(); }
            $_POST['cats'][] = (int)$cid;
        }
        if ($id && !isset($body['categories']) && !isset($body['category'])) $_POST['cats'] = db()->query("SELECT category_id FROM product_category WHERE product_id=$id")->fetchAll(PDO::FETCH_COLUMN);
        if (isset($body['links']) || !$id) foreach ((array)($body['links'] ?? []) as $l) {
            $q = db()->prepare('SELECT id FROM stores WHERE id=? OR slug=? OR lower(name)=lower(?)'); $q->execute([is_numeric($l['store'] ?? null) ? (int)$l['store'] : 0, slugify((string)($l['store'] ?? '')), (string)($l['store'] ?? '')]);
            if (!($sid = $q->fetchColumn())) throw new RuntimeException('Loja não encontrada: ' . ($l['store'] ?? '?') . '. Cadastre-a no painel.');
            $_POST['links'][] = ['id' => $l['id'] ?? 0, 'store_id' => $sid, 'url' => $l['url'] ?? '', 'price' => $l['price'] ?? '', 'old_price' => $l['old_price'] ?? '', 'button_text' => $l['button_text'] ?? '', 'new_tab' => $l['new_tab'] ?? 1];
        }
        elseif ($id) foreach (db()->query("SELECT * FROM affiliate_links WHERE product_id=$id ORDER BY sort,id")->fetchAll() as $l) $_POST['links'][] = $l;
        foreach ((array)($body['image_urls'] ?? (isset($body['image_url']) ? [$body['image_url']] : [])) as $u) $GLOBALS['api_images'][] = api_fetch_image((string)$u);
        $pid = save_product($id);
    } catch (Throwable $x) { foreach ($GLOBALS['api_images'] as $f) @unlink(ROOT . '/public' . $f); json_out(['error' => $x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.'], $x instanceof RuntimeException ? 422 : 500); }
    $s = db()->query("SELECT slug,sku FROM products WHERE id=$pid")->fetch(); json_out(['id' => $pid, 'slug' => $s['slug'], 'sku' => $s['sku'], 'path' => '/produto/' . $s['slug']], $id ? 200 : 201);
}
function page_admin_api(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        if (($_POST['action'] ?? '') === 'revoke') { db()->prepare('UPDATE api_keys SET revoked=1 WHERE id=?')->execute([(int)$_POST['id']]); flash('Chave revogada.'); }
        else { $k = 'ak_' . bin2hex(random_bytes(20)); db()->prepare('INSERT INTO api_keys(name,key_hash) VALUES(?,?)')->execute([mb_substr(trim((string)($_POST['name'] ?? '')) ?: 'Automação', 0, 60), hash('sha256', $k)]); $_SESSION['new_key'] = $k; }
        redirect('/admin/api');
    }
    admin_head('API de integração', 'api'); $nk = $_SESSION['new_key'] ?? null; unset($_SESSION['new_key']);
    if ($nk) echo '<div class="flash">Copie agora, ela não será mostrada de novo:<br><code class="key">' . e($nk) . '</code></div>';
    echo '<form class="form card-form" method="post">' . csrf_field() . '<h2>Nova chave</h2><label>Nome (ex.: n8n)<input name="name" maxlength="60"></label><button class="btn" type="submit">Gerar chave</button></form><div class="table-wrap"><table class="tbl"><thead><tr><th>Nome</th><th>Criada</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach (db()->query('SELECT * FROM api_keys ORDER BY id DESC')->fetchAll() as $k) echo '<tr><td>' . e($k['name']) . '</td><td>' . e($k['created_at']) . '</td><td>' . ($k['revoked'] ? 'Revogada' : 'Ativa') . '</td><td>' . ($k['revoked'] ? '' : '<form method="post" data-confirm="Revogar esta chave?">' . csrf_field() . '<input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="' . (int)$k['id'] . '"><button class="btn danger sm">Revogar</button></form>') . '</td></tr>';
    echo '</tbody></table></div><div class="card-form prose"><h2>Documentação</h2><p>Envie a chave no header <code>X-API-Key</code>. Corpo em JSON.</p><ul><li><code>GET /api/v1/stores</code> e <code>GET /api/v1/categories</code></li><li><code>POST /api/v1/products</code> cria produto</li><li><code>PUT /api/v1/products/{id}</code> atualiza (se enviar <code>links</code>, eles substituem os atuais; inclua o <code>id</code> do link para mantê-lo)</li></ul>'
       . '<pre>curl -X POST ' . e((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'seusite.com')) . '/api/v1/products \\
 -H "X-API-Key: SUA_CHAVE" -H "Content-Type: application/json" \\
 -d \'{"title":"Air Fryer 4L","category":"Casa e Cozinha","image_url":"https://exemplo.com/foto.jpg",
      "links":[{"store":"Shopee","url":"https://...","price":299.90,"old_price":399.90}]}\'</pre></div>'; admin_foot();
}
