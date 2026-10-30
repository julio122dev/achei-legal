<?php
const PER_PAGE = 12;
function render_description(string $d): string {
    $d = trim(str_replace("\r", '', $d)); if ($d === '') return '';
    if (preg_match('/<(p|br|ul|ol|li|h3|h4)\b/i', $d)) return strip_tags($d, '<p><br><b><strong><i><em><ul><ol><li><h3><h4>');
    $h = ''; foreach (preg_split('/\n{2,}/', $d) as $para) if (trim($para) !== '') $h .= '<p>' . nl2br(e(trim($para))) . '</p>';
    return $h;
}
function money($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
function off_pct($best, $old): int { return ($old && $best && $old > $best) ? (int)round((1 - $best / $old) * 100) : 0; }
function product_select(): string {
    return "SELECT p.*,
     (SELECT path FROM product_images i WHERE i.product_id=p.id ORDER BY sort,id LIMIT 1) AS cover,
     (SELECT MIN(price) FROM affiliate_links l WHERE l.product_id=p.id AND price IS NOT NULL) AS best,
     (SELECT old_price FROM affiliate_links l WHERE l.product_id=p.id AND price IS NOT NULL ORDER BY price LIMIT 1) AS best_old,
     (SELECT COUNT(DISTINCT store_id) FROM affiliate_links l WHERE l.product_id=p.id) AS n_stores FROM products p";
}
function product_card(array $p, bool $forceFeat = false): string {
    $off = off_pct($p['best'], $p['best_old']); $u = '/produto/' . e($p['slug']); $n = (int)$p['n_stores'];
    $img = $p['cover'] ? '<img src="' . e($p['cover']) . '" alt="' . e($p['title']) . '" loading="lazy" width="400" height="400">' : '<div class="noimg">Sem foto</div>';
    return '<article class="pc"><div class="pc-img"><a href="' . $u . '">' . $img . '</a>'
        . (($p['featured'] || $forceFeat) ? '<span class="tag feat">DESTAQUE</span>' : '') . ($off ? '<span class="tag off">' . $off . '% OFF</span>' : '')
        . '<button class="heart" type="button" data-fav="' . (int)$p['id'] . '" aria-label="Favoritar" aria-pressed="false">' . ICON_HEART . '</button></div>'
        . '<div class="pc-b"><h3><a href="' . $u . '">' . e($p['title']) . '</a></h3>'
        . ($off ? '<s class="old">' . money($p['best_old']) . '</s>' : '')
        . ($p['best'] !== null ? '<strong class="price">' . money($p['best']) . '</strong>' : '<span class="muted">Consulte nas lojas</span>')
        . '<small>' . $n . ($n === 1 ? ' loja disponível' : ' lojas disponíveis') . '</small></div></article>';
}
function category_options(int $sel = 0, int $skip = 0): string {
    $all = db()->query('SELECT id,name,parent_id FROM categories ORDER BY sort,name')->fetchAll(); $o = '';
    $walk = function (?int $par, int $d) use (&$walk, $all, $sel, $skip, &$o) {
        foreach ($all as $c) if ($c['parent_id'] == $par && $c['id'] != $skip) {
            $o .= '<option value="' . $c['id'] . '"' . ($c['id'] == $sel ? ' selected' : '') . '>' . str_repeat('— ', $d) . e($c['name']) . '</option>'; $walk((int)$c['id'], $d + 1);
        }
    };
    $walk(null, 0); return $o;
}
function featured_products(): array {
    $auto = setting('featured_auto', '0') === '1'; $out = []; $seen = []; $sel = product_select();
    $add = function (array $rows) use (&$out, &$seen) { foreach ($rows as $r) if (count($out) < 12 && !isset($seen[$r['id']])) { $seen[$r['id']] = 1; $out[] = $r; } };
    if ($auto) {   // automático: mais clicados/vistos em 7 dias; se faltar, completa com os marcados e depois com os mais novos
        $sc = "((SELECT COUNT(*) FROM click_log c WHERE c.product_id=p.id AND c.created_at>datetime('now','-7 days'))*3 + (SELECT COUNT(*) FROM views_log v WHERE v.product_id=p.id AND v.created_at>datetime('now','-7 days')))";
        $add(db()->query("$sel WHERE p.status='published' AND $sc>0 ORDER BY $sc DESC, p.id DESC LIMIT 12")->fetchAll());
    }
    $add(db()->query("$sel WHERE p.status='published' AND p.featured=1 ORDER BY p.id DESC LIMIT 12")->fetchAll());
    if ($auto) $add(db()->query("$sel WHERE p.status='published' ORDER BY p.id DESC LIMIT 12")->fetchAll());
    return $out;
}
function home_products(): void {
    $f = featured_products();
    $n = db()->query(product_select() . " WHERE p.status='published' ORDER BY p.id DESC LIMIT 8")->fetchAll();
    if ($f && setting('show_featured', '1') === '1') { echo '<section class="wrap sec"><div class="sec-hd"><h2>Destaques da semana</h2><a href="/produtos">Ver todos</a></div><div class="hs" data-hs><button class="hs-a prev" type="button" aria-label="Anterior">‹</button><div class="hscroll">'; foreach ($f as $p) echo product_card($p, true); echo '</div><button class="hs-a next" type="button" aria-label="Próximo">›</button><div class="hs-d" aria-label="Posição no carrossel"></div></div></section>'; }
    if ($n && setting('show_latest', '1') === '1') { echo '<section class="wrap sec"><div class="sec-hd"><h2>Últimos achadinhos</h2><a href="/produtos">Ver todos</a></div><div class="pgrid">'; foreach ($n as $p) echo product_card($p); echo '</div></section>'; }
}
function page_products(?string $t = null, ?int $forceCat = null, ?int $storeId = null, string $pre = ''): void {
    $title = $t ?? 'Produtos'; if ($forceCat) $_GET['cat'] = $forceCat; $w = ["p.status='published'"]; $a = [];
    if (($q = trim((string)($_GET['q'] ?? ''))) !== '') { $w[] = '(p.title LIKE ? OR p.keywords LIKE ? OR p.sku LIKE ?)'; $l = '%' . $q . '%'; array_push($a, $l, $l, $l); }
    $cat = (int)($_GET['cat'] ?? 0);
    if ($cat) { $ids = array_merge([$cat], db()->query('SELECT id FROM categories WHERE parent_id=' . $cat)->fetchAll(PDO::FETCH_COLUMN)); $w[] = 'p.id IN (SELECT product_id FROM product_category WHERE category_id IN (' . implode(',', array_map('intval', $ids)) . '))'; }
    if (!empty($_GET['fav'])) { $ids = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))); $w[] = 'p.id IN (' . ($ids ? implode(',', $ids) : '0') . ')'; $title = 'Meus favoritos'; }
    if ($storeId) { $w[] = 'p.id IN (SELECT product_id FROM affiliate_links WHERE store_id=?)'; $a[] = $storeId; }
    $orders = ['new' => 'p.id DESC', 'old' => 'p.id ASC', 'low' => '(best IS NULL), best ASC', 'high' => 'best DESC', 'az' => 'p.title COLLATE NOCASE ASC', 'za' => 'p.title COLLATE NOCASE DESC'];
    $sort = isset($orders[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'new';
    $where = implode(' AND ', $w);
    $c = db()->prepare("SELECT COUNT(*) FROM products p WHERE $where"); $c->execute($a); $total = (int)$c->fetchColumn();
    $pages = max(1, (int)ceil($total / PER_PAGE)); $pg = min($pages, max(1, (int)($_GET['page'] ?? 1)));
    $s = db()->prepare(product_select() . " WHERE $where ORDER BY {$orders[$sort]} LIMIT " . PER_PAGE . ' OFFSET ' . (($pg - 1) * PER_PAGE)); $s->execute($a);
    public_head($title . ' · ' . site_name());
    echo '<section class="wrap sec">' . $pre . '<h1>' . e($title) . '</h1><form class="filters" method="get" action="' . e(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) . '"><input name="q" value="' . e($q) . '" placeholder="Buscar..." aria-label="Buscar" maxlength="80">'
       . '<select name="cat" aria-label="Categoria"><option value="">Todas as categorias</option>' . category_options($cat) . '</select><select name="sort" aria-label="Ordenar">';
    foreach (['new' => 'Mais recentes', 'old' => 'Mais antigos', 'low' => 'Menor preço', 'high' => 'Maior preço', 'az' => 'A-Z', 'za' => 'Z-A'] as $k => $v) echo '<option value="' . $k . '"' . ($k === $sort ? ' selected' : '') . '>' . $v . '</option>';
    echo '</select><button class="btn" type="submit">Buscar</button>' . ($q . $cat !== '' && $q . $cat !== '0' ? '<a class="clear" href="/produtos">Limpar filtros</a>' : '') . '</form>';
    if (!empty($_GET['fav'])) echo '<script>if(!location.search.includes("ids=")){try{var f=JSON.parse(localStorage.getItem("favs")||"[]");location.replace("/produtos?fav=1&ids="+f.join(","))}catch(e){}}</script>';
    if (!$total) echo '<p class="empty">Nenhum produto encontrado.</p>';
    else { echo '<div class="pgrid">'; foreach ($s->fetchAll() as $p) echo product_card($p); echo '</div>'; }
    if ($pages > 1) {
        echo '<nav class="pager" aria-label="Páginas">';
        $qs = fn(int $n) => e(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) . '?' . http_build_query(array_merge($_GET, ['page' => $n]));
        if ($pg > 1) echo '<a href="' . ($qs($pg - 1)) . '">‹</a>';
        for ($i = 1; $i <= $pages; $i++) if ($i === 1 || $i === $pages || abs($i - $pg) <= 1) echo '<a' . ($i === $pg ? ' class="on" aria-current="page"' : '') . ' href="' . e($qs($i)) . '">' . $i . '</a>'; elseif (abs($i - $pg) === 2) echo '<span>…</span>';
        if ($pg < $pages) echo '<a href="' . e($qs($pg + 1)) . '">›</a>';
        echo '</nav>';
    }
    echo '</section>'; public_foot();
}
function page_product(string $slug): void {
    $q = db()->prepare(product_select() . " WHERE p.slug=? AND p.status='published'"); $q->execute([$slug]); $p = $q->fetch();
    if (!$p) { page_soon(); return; }
    $h = client_hash(); $c = db()->prepare("SELECT 1 FROM views_log WHERE product_id=? AND ip_hash=? AND created_at>datetime('now','-30 minutes')"); $c->execute([$p['id'], $h]);
    if (!$c->fetch()) { db()->prepare('INSERT INTO views_log(product_id,ip_hash) VALUES(?,?)')->execute([$p['id'], $h]); db()->prepare('UPDATE products SET views=views+1 WHERE id=?')->execute([$p['id']]); $p['views']++; }
    $im = db()->prepare('SELECT path FROM product_images WHERE product_id=? ORDER BY sort,id'); $im->execute([$p['id']]); $imgs = $im->fetchAll(PDO::FETCH_COLUMN);
    $ln = db()->prepare('SELECT l.*, s.name sname, s.logo slogo, s.color scolor FROM affiliate_links l JOIN stores s ON s.id=l.store_id AND s.active=1 WHERE l.product_id=? ORDER BY (l.price IS NULL), l.price, l.sort'); $ln->execute([$p['id']]); $links = $ln->fetchAll();
    $ct = db()->prepare('SELECT c.id,c.name FROM categories c JOIN product_category pc ON pc.category_id=c.id WHERE pc.product_id=? LIMIT 1'); $ct->execute([$p['id']]); $cat = $ct->fetch();
    $off = off_pct($p['best'], $p['best_old']); $url = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/produto/' . $p['slug'];
    $hasV = video_html($p) !== '';
    public_head($p['title'] . ' · ' . site_name());
    echo '<div class="wrap sec"><nav class="crumb"><a href="/">Início</a> › ' . ($cat ? '<a href="/produtos?cat=' . (int)$cat['id'] . '">' . e($cat['name']) . '</a> › ' : '') . e($p['title']) . '</nav>'
       . '<div class="pd"><div class="gal"><div class="gal-main">' . video_html($p) . ($imgs ? '<img id="galMain"' . ($hasV ? ' hidden' : '') . ' src="' . e($imgs[0]) . '" alt="' . e($p['title']) . '">' : ($hasV ? '' : '<div class="noimg">Sem foto</div>')) . '</div>';
    if (count($imgs) > ($hasV ? 0 : 1)) { echo '<div class="gal-th">' . ($hasV ? '<button type="button" data-galv aria-label="Ver vídeo">▶</button>' : ''); foreach ($imgs as $i => $src) echo '<button type="button" data-gal="' . e($src) . '" aria-label="Foto ' . ($i + 1) . '"><img src="' . e($src) . '" alt="" loading="lazy"></button>'; echo '</div>'; }
    echo '</div><div class="pd-info"><h1>' . e($p['title']) . '</h1><div class="badges">' . ($p['sku'] ? '<span class="badge">' . e($p['sku']) . '</span>' : '') . '<span class="badge">' . (int)$p['views'] . ' visualizações</span></div>';
    if ($p['short_desc']) echo '<p class="muted">' . e($p['short_desc']) . '</p>';
    if ($p['best'] !== null) echo '<div class="best"><small>Melhor preço encontrado:</small><div><strong class="price big">' . money($p['best']) . '</strong>' . ($off ? ' <s class="old">' . money($p['best_old']) . '</s> <span class="tag off">' . $off . '% OFF</span>' : '') . '</div></div>';
    echo '<h2 class="sub">Lojas disponíveis</h2>';
    if (!$links) echo '<p class="empty">Nenhuma loja disponível no momento.</p>';
    foreach ($links as $l) {
        echo '<div class="lrow" style="--sc:' . e($l['scolor']) . '"><div class="lstore">' . ($l['slogo'] ? '<img src="' . e($l['slogo']) . '" alt="" width="36" height="36">' : '') . '<div><strong>' . e($l['sname']) . '</strong><br>'
           . ($l['price'] !== null ? '<span class="price">' . money($l['price']) . '</span>' : '<span class="muted">Ver preço na loja</span>') . '</div></div>'
           . '<a class="btn" href="/ir/' . (int)$l['id'] . '" rel="nofollow sponsored noopener"' . ($l['new_tab'] ? ' target="_blank"' : '') . '>' . e($l['button_text'] ?: 'Ir para loja') . '</a></div>';
    }
    echo '<p class="safe">🔒 Transações seguras na loja · Preços sujeitos a alteração</p><div class="share"><span>Compartilhar:</span>'
       . '<a href="https://wa.me/?text=' . rawurlencode($p['title'] . ' ' . $url) . '" target="_blank" rel="noopener">WhatsApp</a>'
       . '<a href="https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($p['title']) . '" target="_blank" rel="noopener">Telegram</a>'
       . '<button type="button" data-copy="' . e($url) . '">Copiar link</button><button type="button" data-mail>E-mail</button><button class="heart in" type="button" data-fav="' . (int)$p['id'] . '" aria-pressed="false" aria-label="Favoritar">' . ICON_HEART . '</button></div></div></div>';
    $d = render_description((string)$p['description']);
    echo '<section class="desc"><h2>Descrição</h2>' . ($d !== '' ? $d : '<p class="muted">Sem descrição.</p>') . '<p class="legal">' . e(setting('legal', 'Este site contém links de afiliado: podemos receber uma comissão por compras feitas por eles, sem custo extra para você. Preços e disponibilidade são das lojas e podem mudar.')) . '</p></section>';
    $rel = db()->prepare(product_select() . " WHERE p.status='published' AND p.id!=? AND p.id IN (SELECT product_id FROM product_category WHERE category_id IN (SELECT category_id FROM product_category WHERE product_id=?)) ORDER BY p.id DESC LIMIT 4"); $rel->execute([$p['id'], $p['id']]);
    if ($r = $rel->fetchAll()) { echo '<h2>Produtos relacionados</h2><div class="pgrid">'; foreach ($r as $x) echo product_card($x); echo '</div>'; }
    $ld = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['title'], 'image' => $imgs ?: null, 'description' => $p['short_desc'] ?: $p['title'], 'sku' => $p['sku']];
    if ($p['best'] !== null) $ld['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'BRL', 'price' => number_format((float)$p['best'], 2, '.', ''), 'url' => $url];
    echo share_dialog($p, $url) . '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script></div>';
    public_foot();
}
function page_go(int $id): void {
    $q = db()->prepare('SELECT * FROM affiliate_links WHERE id=?'); $q->execute([$id]); $l = $q->fetch();
    if (!$l || !preg_match('#^https?://#i', $l['url'])) { page_soon(); return; }
    db()->prepare('INSERT INTO click_log(link_id,product_id,store_id) VALUES(?,?,?)')->execute([$l['id'], $l['product_id'], $l['store_id']]);
    header('X-Robots-Tag: noindex'); header('Location: ' . $l['url'], true, 302); exit;
}
