<?php
function parse_price($s): ?float {
    $s = trim(str_replace(['R$', ' '], '', (string)$s)); if ($s === '') return null;
    if (str_contains($s, ',')) $s = str_replace(',', '.', str_replace('.', '', $s));
    return is_numeric($s) ? max(0.0, (float)$s) : null;
}
function page_admin_categories(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check(); $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        if ($a === 'add' && ($n = trim((string)($_POST['name'] ?? ''))) !== '' && mb_strlen($n) <= 60) {
            db()->prepare('INSERT INTO categories(name,slug,parent_id,sort) VALUES(?,?,?,?)')->execute([$n, unique_slug('categories', $n), ((int)($_POST['parent_id'] ?? 0)) ?: null, (int)($_POST['sort'] ?? 0)]); flash('Categoria adicionada.');
        } elseif ($a === 'delete') {
            db()->prepare('UPDATE categories SET parent_id=NULL WHERE parent_id=?')->execute([$id]); db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); flash('Categoria removida.');
        } else flash('Informe o nome da categoria (até 60 caracteres).', 'err');
        redirect('/admin/categorias');
    }
    admin_head('Categorias', 'categorias');
    echo '<form class="form card-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="add"><h2>Adicionar categoria</h2><div class="grid2"><label>Nome<input name="name" required maxlength="60"></label>'
       . '<label>Categoria-pai<select name="parent_id"><option value="">— Nenhuma —</option>' . category_options() . '</select></label><label>Ordem<input type="number" name="sort" value="0"></label></div><button class="btn" type="submit">Adicionar</button></form>'
       . '<div class="table-wrap"><table class="tbl"><thead><tr><th>Nome</th><th>Produtos</th><th></th></tr></thead><tbody>';
    $rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM product_category pc WHERE pc.category_id=c.id) n FROM categories c ORDER BY COALESCE(parent_id,id), parent_id IS NOT NULL, sort, name')->fetchAll();
    if (!$rows) echo '<tr><td colspan="3" class="empty">Nenhuma categoria ainda.</td></tr>';
    foreach ($rows as $c) echo '<tr><td>' . ($c['parent_id'] ? '— ' : '<strong>') . e($c['name']) . ($c['parent_id'] ? '' : '</strong>') . '</td><td>' . (int)$c['n'] . '</td><td><form method="post" data-confirm="Remover a categoria ' . e($c['name']) . '?">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></td></tr>';
    echo '</tbody></table></div>'; admin_foot();
}
function page_admin_products(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        csrf_check(); delete_product((int)$_POST['id']); flash('Produto removido.'); redirect('/admin/produtos');
    }
    $q = trim((string)($_GET['q'] ?? '')); $l = '%' . $q . '%';
    $s = db()->prepare(product_select() . ' WHERE p.title LIKE ? OR p.sku LIKE ? ORDER BY p.id DESC LIMIT 200'); $s->execute([$l, $l]);
    admin_head('Produtos', 'produtos');
    echo '<div class="bar"><form method="get" class="inline"><input name="q" value="' . e($q) . '" placeholder="Buscar por título ou SKU"><button class="btn sm" type="submit">Buscar</button></form><a class="btn" href="/admin/produtos/novo">+ Novo produto</a></div>'
       . '<div class="table-wrap"><table class="tbl"><thead><tr><th></th><th>Produto</th><th>Melhor preço</th><th>Status</th><th></th></tr></thead><tbody>';
    $rows = $s->fetchAll(); if (!$rows) echo '<tr><td colspan="5" class="empty">Nenhum produto.</td></tr>';
    foreach ($rows as $p) echo '<tr><td>' . ($p['cover'] ? '<img class="thumb" src="' . e($p['cover']) . '" alt="">' : '<span class="thumb ph">?</span>') . '</td><td><strong>' . e($p['title']) . '</strong>' . ($p['featured'] ? ' ★' : '') . '<br><small class="muted">' . e($p['sku']) . ' · ' . (int)$p['n_stores'] . ' loja(s)</small></td><td>' . ($p['best'] !== null ? money($p['best']) : '—') . '</td>'
        . '<td><span class="pill ' . ($p['status'] === 'published' ? 'on' : '') . '">' . ($p['status'] === 'published' ? 'Publicado' : 'Rascunho') . '</span></td><td class="act"><a class="btn sm" href="/admin/produtos/' . (int)$p['id'] . '">Editar</a>'
        . '<form method="post" data-confirm="Excluir ' . e($p['title']) . '?">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$p['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></td></tr>';
    echo '</tbody></table></div>'; admin_foot();
}
function delete_product(int $id): void {
    $v = db()->prepare('SELECT video_type,video_url FROM products WHERE id=?'); $v->execute([$id]); if (($r = $v->fetch()) && $r['video_type'] === 'upload') drop_video($r['video_url']);
    $q = db()->prepare('SELECT path FROM product_images WHERE product_id=?'); $q->execute([$id]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $f) if (str_starts_with($f, '/uploads/products/')) @unlink(ROOT . '/public' . $f);
    db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
}
function link_row($i, array $l, array $stores): string {
    $o = ''; foreach ($stores as $s) $o .= '<option value="' . (int)$s['id'] . '"' . (($l['store_id'] ?? 0) == $s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
    $f = fn($v) => $v !== null && $v !== '' ? (is_string($v) && !is_numeric($v) ? $v : number_format((float)$v, 2, ',', '')) : '';
    return '<div class="lk" data-row><input type="hidden" name="links[' . $i . '][id]" value="' . (int)($l['id'] ?? 0) . '"><div class="grid2"><label>Loja<select name="links[' . $i . '][store_id]" required>' . $o . '</select></label>'
        . '<label>URL de afiliado<input name="links[' . $i . '][url]" type="url" required maxlength="2000" value="' . e($l['url'] ?? '') . '" placeholder="https://..."></label>'
        . '<label>Preço (R$)<input name="links[' . $i . '][price]" inputmode="decimal" value="' . e($f($l['price'] ?? null)) . '" placeholder="99,90"></label>'
        . '<label>Preço antigo (R$)<input name="links[' . $i . '][old_price]" inputmode="decimal" value="' . e($f($l['old_price'] ?? null)) . '"></label>'
        . '<label>Texto do botão (opcional)<input name="links[' . $i . '][button_text]" maxlength="40" value="' . e($l['button_text'] ?? '') . '"></label>'
        . '<label class="chk"><input type="checkbox" name="links[' . $i . '][new_tab]" value="1"' . (($l['new_tab'] ?? 1) ? ' checked' : '') . '> Abrir em nova aba</label></div><button type="button" class="btn danger sm" data-del-row>Remover link</button></div>';
}
function save_product(?int $id): int {
    $GLOBALS['save_warnings'] = [];
    $title = trim((string)($_POST['title'] ?? '')); if ($title === '' || mb_strlen($title) > 160) throw new RuntimeException('Informe o título (até 160 caracteres).');
    $GLOBALS['api_images'] ??= [];
    foreach ((array)($_POST['import_images'] ?? []) as $ip) if (is_string($ip) && preg_match('#^/uploads/products/[a-f0-9]{16}\.png$#', $ip) && is_file(ROOT . '/public' . $ip)) $GLOBALS['api_images'][] = $ip;
    $f = [mb_substr(trim((string)($_POST['short_desc'] ?? '')), 0, 240), strip_tags((string)($_POST['description'] ?? ''), '<p><br><b><strong><i><em><ul><ol><li><h3><h4>'),
        trim((string)($_POST['keywords'] ?? '')), !empty($_POST['featured']) ? 1 : 0, ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published'];
    $cats = array_unique(array_filter(array_map('intval', (array)($_POST['cats'] ?? []))));
    db()->beginTransaction();
    try {
        if (!$id) {
            db()->prepare('INSERT INTO products(title,slug,short_desc,description,keywords,featured,status,updated_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute([$title, unique_slug('products', $title), ...$f]);
            $id = (int)db()->lastInsertId();
        } else db()->prepare('UPDATE products SET title=?,short_desc=?,description=?,keywords=?,featured=?,status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$title, ...$f, $id]);
        db()->prepare('DELETE FROM product_category WHERE product_id=?')->execute([$id]);
        foreach ($cats as $c) db()->prepare('INSERT OR IGNORE INTO product_category VALUES(?,?)')->execute([$id, $c]);
        $sku = trim((string)($_POST['sku'] ?? ''));
        if ($sku === '') { $p = $cats ? db()->query('SELECT slug FROM categories WHERE id=' . (int)reset($cats))->fetchColumn() : 'ach'; $sku = strtoupper(substr(preg_replace('/[^a-z]/', '', (string)$p) . 'ach', 0, 3)) . '-' . sprintf('%04d', $id); }
        db()->prepare('UPDATE products SET sku=? WHERE id=?')->execute([mb_substr($sku, 0, 30), $id]);
        $keep = [];
        foreach (array_values((array)($_POST['links'] ?? [])) as $i => $l) {
            $url = trim((string)($l['url'] ?? '')); if (!preg_match('#^https?://#i', $url) || !(int)($l['store_id'] ?? 0)) continue;
            $v = [(int)$l['store_id'], $url, parse_price($l['price'] ?? ''), parse_price($l['old_price'] ?? ''), mb_substr(trim((string)($l['button_text'] ?? '')), 0, 40) ?: null, empty($l['new_tab']) ? 0 : 1, $i];
            if ((int)($l['id'] ?? 0)) { db()->prepare('UPDATE affiliate_links SET store_id=?,url=?,price=?,old_price=?,button_text=?,new_tab=?,sort=? WHERE id=? AND product_id=?')->execute([...$v, (int)$l['id'], $id]); $keep[] = (int)$l['id']; }
            else { db()->prepare('INSERT INTO affiliate_links(store_id,url,price,old_price,button_text,new_tab,sort,product_id) VALUES(?,?,?,?,?,?,?,?)')->execute([...$v, $id]); $keep[] = (int)db()->lastInsertId(); }
        }
        db()->exec('DELETE FROM affiliate_links WHERE product_id=' . $id . ($keep ? ' AND id NOT IN (' . implode(',', $keep) . ')' : ''));
        db()->commit();
    } catch (Throwable $x) { db()->rollBack(); throw $x; }
    save_product_media($id);
    return $id;
}
function ini_bytes(string $v): int { $n = (float)$v; return (int)match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n }; }
/** Fotos e vídeo: se algo falhar, o produto continua salvo e o motivo vira aviso (nada é apagado). */
function save_product_media(int $id): void {
    $w =& $GLOBALS['save_warnings']; $new = []; $gone = [];
    foreach ((array)($_POST['del_img'] ?? []) as $di) {
        $q = db()->prepare('SELECT path FROM product_images WHERE id=? AND product_id=?'); $q->execute([(int)$di, $id]);
        if ($path = $q->fetchColumn()) { @unlink(ROOT . '/public' . $path); db()->prepare('DELETE FROM product_images WHERE id=?')->execute([(int)$di]); $gone[] = (int)$di; }
    }
    $n = (int)db()->query('SELECT COALESCE(MAX(sort),0) FROM product_images WHERE product_id=' . $id)->fetchColumn(); $F = $_FILES['images'] ?? null; $k = -1;
    if ($F && is_array($F['name'])) foreach (array_keys($F['name']) as $key) {
        if ($F['error'][$key] === UPLOAD_ERR_NO_FILE) continue; $k++;
        try {
            $path = upload_image(['error' => $F['error'][$key], 'size' => $F['size'][$key], 'tmp_name' => $F['tmp_name'][$key]], 'products', 900);
            if ($path) { db()->prepare('INSERT INTO product_images(product_id,path,sort) VALUES(?,?,?)')->execute([$id, $path, ++$n]); $new['n:' . $k] = (int)db()->lastInsertId(); }
        } catch (Throwable $x) { $w[] = 'foto "' . mb_substr((string)$F['name'][$key], 0, 40) . '": ' . $x->getMessage(); }
    }
    foreach ((array)($GLOBALS['api_images'] ?? []) as $path) { db()->prepare('INSERT INTO product_images(product_id,path,sort) VALUES(?,?,?)')->execute([$id, $path, ++$n]); $new['i:' . $path] = (int)db()->lastInsertId(); }
    $order = json_decode((string)($_POST['img_order'] ?? '[]'), true);
    if (is_array($order) && $order) {
        $pos = 0; $seen = [];
        foreach ($order as $tok) { $iid = preg_match('/^e:(\d+)$/', (string)$tok, $m) ? (in_array((int)$m[1], $gone, true) ? 0 : (int)$m[1]) : ($new[$tok] ?? 0); if ($iid && !isset($seen[$iid])) { $seen[$iid] = 1; db()->prepare('UPDATE product_images SET sort=? WHERE id=? AND product_id=?')->execute([++$pos, $iid, $id]); } }
        foreach (db()->query('SELECT id FROM product_images WHERE product_id=' . $id . ' ORDER BY sort,id')->fetchAll(PDO::FETCH_COLUMN) as $iid) if (!isset($seen[$iid])) db()->prepare('UPDATE product_images SET sort=? WHERE id=?')->execute([++$pos, $iid]);
    }
    try {
        $cur = db()->query('SELECT video_type,video_url FROM products WHERE id=' . $id)->fetch();
        $vt = in_array($_POST['video_type'] ?? '', ['youtube', 'upload'], true) ? $_POST['video_type'] : (isset($_POST['video_type']) ? 'none' : $cur['video_type']);
        if ($vt === 'youtube') {
            $u = trim((string)($_POST['video_url'] ?? $cur['video_url'])); if (!yt_id($u)) throw new RuntimeException('o link do YouTube é inválido.');
            if ($cur['video_type'] === 'upload') drop_video($cur['video_url']); db()->prepare("UPDATE products SET video_type='youtube',video_url=? WHERE id=?")->execute([$u, $id]);
        } elseif ($vt === 'upload') {
            $v = upload_video($_FILES['video'] ?? []);
            if ($v) { if ($cur['video_type'] === 'upload') drop_video($cur['video_url']); db()->prepare("UPDATE products SET video_type='upload',video_url=? WHERE id=?")->execute([$v, $id]); }
            elseif ($cur['video_type'] !== 'upload') throw new RuntimeException('escolha o arquivo de vídeo (MP4 ou WebM).');
        } else { if ($cur['video_type'] === 'upload') drop_video($cur['video_url']); db()->prepare("UPDATE products SET video_type='none',video_url=NULL WHERE id=?")->execute([$id]); }
    } catch (Throwable $x) { $w[] = 'vídeo: ' . ($x instanceof RuntimeException ? $x->getMessage() : 'erro ao salvar.'); }
}
function category_checks(array $sel): string {
    $all = db()->query('SELECT id,name,parent_id FROM categories ORDER BY sort,name')->fetchAll(); $o = ''; $sel = array_map('strval', $sel);
    $walk = function (?int $par, int $d) use (&$walk, $all, $sel, &$o) { foreach ($all as $c) if ($c['parent_id'] == $par) { $o .= '<label class="cat-i" style="margin-left:' . ($d * 18) . 'px"><input type="checkbox" name="cats[]" value="' . (int)$c['id'] . '"' . (in_array((string)$c['id'], $sel, true) ? ' checked' : '') . '> ' . e($c['name']) . '</label>'; $walk((int)$c['id'], $d + 1); } };
    $walk(null, 0); return $o;
}
function page_admin_cat_quick(): void {
    require_admin(); if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'msg' => 'Método inválido.'], 405); csrf_check();
    $n = trim((string)($_POST['name'] ?? '')); if ($n === '' || mb_strlen($n) > 60) json_out(['ok' => false, 'msg' => 'Informe o nome da categoria (até 60 caracteres).']);
    $q = db()->prepare('SELECT id,name FROM categories WHERE lower(name)=lower(?)'); $q->execute([$n]); if ($c = $q->fetch()) json_out(['ok' => true, 'id' => (int)$c['id'], 'name' => $c['name']]);
    db()->prepare('INSERT INTO categories(name,slug) VALUES(?,?)')->execute([$n, unique_slug('categories', $n)]); json_out(['ok' => true, 'id' => (int)db()->lastInsertId(), 'name' => $n]);
}
function page_admin_product_form(?int $id): void {
    require_admin();
    $p = ['title' => '', 'short_desc' => '', 'description' => '', 'keywords' => '', 'sku' => '', 'featured' => 0, 'status' => 'published', 'video_type' => 'none', 'video_url' => '']; $links = []; $imgs = []; $sel = [];
    if ($id) {
        $q = db()->prepare('SELECT * FROM products WHERE id=?'); $q->execute([$id]); $p = $q->fetch(); if (!$p) redirect('/admin/produtos');
        $l = db()->prepare('SELECT * FROM affiliate_links WHERE product_id=? ORDER BY sort,id'); $l->execute([$id]); $links = $l->fetchAll();
        $i = db()->prepare('SELECT id,path AS src FROM product_images WHERE product_id=? ORDER BY sort,id'); $i->execute([$id]); $imgs = $i->fetchAll();
        $c = db()->prepare('SELECT category_id FROM product_category WHERE product_id=?'); $c->execute([$id]); $sel = $c->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        try {
            $nid = save_product($id); $w = $GLOBALS['save_warnings'] ?? [];
            flash($w ? 'Produto salvo, mas: ' . implode(' · ', $w) . ' (o resto foi salvo normalmente).' : 'Produto salvo.', $w ? 'err' : 'ok');
            redirect(!empty($_POST['again']) ? '/admin/produtos/novo' : '/admin/produtos/' . $nid);
        } catch (Throwable $x) {
            flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar o produto.', 'err');
            $p = array_merge($p, $_POST); $sel = array_map('intval', (array)($_POST['cats'] ?? []));
            $links = array_map(fn($l) => $l + ['new_tab' => 0], array_values((array)($_POST['links'] ?? [])));
        }
    }
    $stores = db()->query('SELECT id,name FROM stores WHERE active=1 ORDER BY sort,name')->fetchAll();
    admin_head($id ? 'Editar produto' : 'Novo produto', 'produtos');
    if (!$stores) echo '<div class="flash err">Cadastre pelo menos uma <a href="/admin/lojas">loja</a> antes de adicionar links de afiliado.</div>';
    echo '<div class="bar"><a class="btn light" href="/admin/produtos">← Lista de produtos</a>' . ($id ? '<a class="btn" href="/admin/produtos/novo">+ Novo produto</a>' : '') . '</div>'
       . '<form id="prodForm" class="form" method="post" enctype="multipart/form-data" data-maxfile="' . ini_bytes(ini_get('upload_max_filesize')) . '" data-maxpost="' . ini_bytes(ini_get('post_max_size')) . '">' . csrf_field()
       . '<div class="card-form form"><h2>Importar da Shopee</h2><label>Cole o link do produto<input id="impUrl" type="url" placeholder="https://shopee.com.br/... ou https://s.shopee.com.br/..."></label><button type="button" class="btn light" id="impBtn">Importar por link</button><p class="muted">Preenche título, foto, preço e link de afiliado. Você confere antes de salvar.</p><p class="msg-out" id="impOut" role="status"></p></div>'
       . '<div class="card-form form"><label>Título*<input name="title" required maxlength="160" value="' . e($p['title']) . '"></label>'
       . '<label>Descrição curta (máx. 240)<input name="short_desc" maxlength="240" value="' . e($p['short_desc']) . '"></label>'
       . '<div class="form"><strong>Descrição completa</strong><span class="muted">Os parágrafos que você separar aqui aparecem separados no site, do jeito que estiver escrito.</span>'
       . '<div class="sizes"><span>Tamanho da caixa:</span><button type="button" data-size="s">Pequeno</button><button type="button" data-size="m">Médio</button><button type="button" data-size="l">Grande</button></div>'
       . '<textarea id="descBox" name="description" rows="5">' . e($p['description']) . '</textarea></div>'
       . '<div class="cats" data-cats><strong>Categorias</strong> <span class="muted" data-cat-sum></span><div class="cat-panel" data-cat-panel><div class="cat-list">' . category_checks($sel) . '</div>'
       . '<div class="cat-new"><input id="catNew" maxlength="60" placeholder="Não achou? Digite uma nova categoria"><button type="button" class="btn light sm" id="catNewBtn">+ Criar categoria</button></div><p class="msg-out" id="catMsg"></p>'
       . '<div class="act"><button type="button" class="btn light sm" data-cat-none>Nenhuma categoria</button><button type="button" class="btn sm" data-cat-ok>OK</button></div></div><button type="button" class="btn light sm" data-cat-edit hidden>Alterar categorias</button></div>'
       . '<div class="grid2"><label>SKU (código interno)<input name="sku" maxlength="30" value="' . e($p['sku']) . '"><small class="muted">O código do produto na sua organização, como CAS-0001. Ajuda você a achar o produto na busca. Se deixar vazio, o site cria sozinho.</small></label>'
       . '<label>Palavras-chave<input name="keywords" value="' . e($p['keywords']) . '"><small class="muted">Palavras que o cliente pode digitar na busca do site para achar este produto, separadas por vírgula. Ex.: fritadeira, air fryer, sem óleo.</small></label></div>'
       . '<label class="chk"><input type="checkbox" name="featured" value="1"' . ($p['featured'] ? ' checked' : '') . '> Destaque da semana (vale no modo manual)</label>'
       . '<label>Status<select name="status"><option value="published">Publicado</option><option value="draft"' . ($p['status'] === 'draft' ? ' selected' : '') . '>Rascunho (não aparece no site)</option></select></label></div>'
       . '<div class="card-form"><h2>Links de afiliado</h2><div id="links">';
    foreach ($links as $i => $l) echo link_row($i, $l, $stores);
    echo '</div><template id="linkTpl">' . link_row('__i__', [], $stores) . '</template><button class="btn light" type="button" data-add-link ' . ($stores ? '' : 'disabled') . '>+ Adicionar link</button></div>'
       . '<div class="card-form form"><h2>Vídeo do produto</h2><label>Tipo<select name="video_type"><option value="none">Sem vídeo</option><option value="youtube"' . ($p['video_type'] === 'youtube' ? ' selected' : '') . '>YouTube (recomendado)</option><option value="upload"' . ($p['video_type'] === 'upload' ? ' selected' : '') . '>Enviar arquivo (MP4/WebM até 50 MB)</option></select></label>'
       . '<label>Link do YouTube<input name="video_url" value="' . e($p['video_type'] === 'youtube' ? $p['video_url'] : '') . '" placeholder="https://www.youtube.com/watch?v=..."></label><label>Arquivo de vídeo<input type="file" name="video" accept="video/mp4,video/webm"></label>'
       . '<small class="muted">Limite atual do servidor por arquivo: ' . e(ini_get('upload_max_filesize')) . '. Vídeos longos ficam melhores no YouTube.</small></div>'
       . '<div class="card-form form"><h2>Fotos</h2><p class="muted">A primeira é a capa. Arraste para mudar a ordem (ou use ◀ ▶) e clique na foto para ampliar. Aparece aqui antes de salvar.</p>'
       . '<div id="imgGrid" class="imgs"></div><div id="imgHidden"></div><label>Adicionar fotos (JPG, PNG ou WebP, até 8 MB cada)<input type="file" id="imgInput" accept="image/png,image/jpeg,image/webp" multiple></label>'
       . '<input type="file" name="images[]" id="imgReal" multiple hidden><input type="hidden" name="img_order" id="imgOrder"></div>'
       . '<div class="act"><button class="btn" type="submit">Salvar produto</button><button class="btn light" type="submit" name="again" value="1">Salvar e adicionar outro</button></div></form>'
       . '<dialog id="zoomDlg" class="dlg zoom"><img id="zoomImg" alt="Foto ampliada"><button type="button" class="btn" data-close>Fechar</button></dialog>'
       . '<script>window.IMGS=' . json_encode($imgs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) . ';</script>';
    admin_foot();
}
