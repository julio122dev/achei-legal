<?php
const PAGE_DEFAULTS = [
 'faq' => ['Perguntas frequentes', '<h3>Como o site funciona?</h3><p>Reunimos achadinhos de várias lojas e mostramos onde cada produto está mais barato. Ao clicar em "Ir para loja", você finaliza a compra no site da própria loja.</p><h3>Vocês vendem os produtos?</h3><p>Não. Somos uma vitrine: a venda, o pagamento e a entrega são feitos pelas lojas.</p><h3>Pago algo a mais por usar o site?</h3><p>Não. Os links são de afiliado e podemos receber uma comissão da loja, sem custo extra para você.</p><h3>Os preços estão sempre atualizados?</h3><p>Os preços podem mudar a qualquer momento. Confirme o valor final na loja antes de comprar.</p>'],
 'termos' => ['Termos de uso', '<p>Este site apresenta produtos e links de afiliado de lojas parceiras. Não realizamos vendas, entregas ou trocas.</p><p>Preços, estoque e condições são de responsabilidade das lojas e podem mudar sem aviso.</p><p>Podemos receber comissão por compras feitas pelos links, sem custo extra para você.</p><p><small>Texto-modelo: revise com um profissional antes de publicar.</small></p>'],
 'privacidade' => ['Política de privacidade', '<p>Guardamos apenas o necessário: mensagens enviadas pelo formulário de contato e contagens de visitas e cliques, sem identificar você (usamos um código anônimo).</p><p>Seus favoritos ficam salvos apenas no seu navegador.</p><p>As lojas parceiras têm suas próprias políticas de privacidade.</p><p><small>Texto-modelo: revise com um profissional antes de publicar.</small></p>'],
];
const PAGE_TAGS = '<p><br><b><strong><i><em><ul><ol><li><h3><h4><a>';
function page_row(string $slug): array { $q = db()->prepare('SELECT title,content FROM pages WHERE slug=?'); $q->execute([$slug]); $r = $q->fetch(); return $r ?: ['title' => PAGE_DEFAULTS[$slug][0], 'content' => PAGE_DEFAULTS[$slug][1]]; }
function page_info(string $slug): void {
    $r = page_row($slug); public_head($r['title'] . ' · ' . site_name());
    echo '<section class="wrap sec prose"><h1>' . e($r['title']) . '</h1>' . strip_tags($r['content'], PAGE_TAGS) . '</section>'; public_foot();
}
function page_contact(): void {
    $err = ''; $ok = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check(); $h = 'c:' . client_hash(); $n = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND ts>?'); $n->execute([$h, time() - 3600]);
        $nm = trim((string)($_POST['name'] ?? '')); $em = trim((string)($_POST['email'] ?? '')); $msg = trim((string)($_POST['message'] ?? ''));
        if ((int)$n->fetchColumn() >= 5) $err = 'Muitas mensagens em pouco tempo. Tente mais tarde.';
        elseif (mb_strlen($nm) < 2 || !filter_var($em, FILTER_VALIDATE_EMAIL) || mb_strlen($msg) < 10 || mb_strlen($msg) > 2000) $err = 'Preencha nome, e-mail válido e uma mensagem de pelo menos 10 caracteres.';
        elseif (!empty($_POST['website']) || time() - (int)($_SESSION['contact_t'] ?? 0) < 3) $err = 'Não foi possível enviar agora. Aguarde alguns segundos e tente de novo.';
        else {
            db()->prepare('INSERT INTO messages(name,email,whatsapp,body) VALUES(?,?,?,?)')->execute([mb_substr($nm, 0, 80), $em, mb_substr(trim((string)($_POST['whatsapp'] ?? '')), 0, 30), $msg]);
            db()->prepare('INSERT INTO login_attempts(ip_hash,ts) VALUES(?,?)')->execute([$h, time()]);
            if (($to = setting('contact_email')) !== '') @mail($to, '=?UTF-8?B?' . base64_encode('Nova mensagem do site') . '?=', "Nome: $nm\nE-mail: $em\n\n$msg", "Reply-To: $em\r\nContent-Type: text/plain; charset=UTF-8");
            $ok = true;
        }
    }
    $_SESSION['contact_t'] = time();
    public_head('Contato · ' . site_name());
    echo '<section class="wrap sec"><h1>Fale conosco</h1><div class="ct"><div>';
    if ($ok) echo '<div class="flash">Mensagem enviada! Responderemos em breve.</div>'; elseif ($err) echo '<div class="flash err" role="alert">' . e($err) . '</div>';
    echo '<form class="form card-form" method="post">' . csrf_field() . '<label>Nome completo*<input name="name" required maxlength="80" value="' . e($ok ? '' : ($_POST['name'] ?? '')) . '"></label><label>E-mail*<input type="email" name="email" required value="' . e($ok ? '' : ($_POST['email'] ?? '')) . '"></label>'
       . '<label>WhatsApp<input name="whatsapp" maxlength="30" inputmode="tel"></label><label>Mensagem*<textarea name="message" rows="5" required maxlength="2000">' . e($ok ? '' : ($_POST['message'] ?? '')) . '</textarea></label>'
       . '<input class="hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn" type="submit">Enviar mensagem</button></form></div><aside class="ct-side">';
    foreach ([['E-mail', setting('contact_email')], ['WhatsApp', setting('contact_whatsapp')], ['Horário de atendimento', setting('contact_hours')]] as [$l, $v]) if ($v !== '') echo '<div class="card"><span>' . $l . '</span><strong>' . e($v) . '</strong></div>';
    echo '</aside></div></section>'; public_foot();
}
function page_categories(): void {
    $all = db()->query('SELECT c.*, (SELECT COUNT(*) FROM product_category pc WHERE pc.category_id=c.id) n FROM categories c ORDER BY sort,name')->fetchAll();
    public_head('Categorias · ' . site_name()); echo '<section class="wrap sec"><h1>Categorias</h1>';
    if (!$all) echo '<p class="empty">Nenhuma categoria ainda.</p>';
    foreach ($all as $c) if (!$c['parent_id']) {
        echo '<div class="catbox"><h2><a href="/categoria/' . e($c['slug']) . '">' . e($c['name']) . '</a> <small>' . (int)$c['n'] . '</small></h2><div class="chips">';
        foreach ($all as $s) if ($s['parent_id'] == $c['id']) echo '<a href="/categoria/' . e($s['slug']) . '">' . e($s['name']) . '</a>';
        echo '</div></div>';
    }
    echo '</section>'; public_foot();
}
function page_category(string $slug): void {
    $q = db()->prepare('SELECT * FROM categories WHERE slug=?'); $q->execute([$slug]); $c = $q->fetch(); if (!$c) { page_soon(); return; }
    page_products('Produtos em ' . $c['name'], (int)$c['id']);
}
function page_stores(): void {
    $stores = db()->query('SELECT s.*, (SELECT COUNT(DISTINCT product_id) FROM affiliate_links l WHERE l.store_id=s.id) n FROM stores s WHERE active=1 ORDER BY sort,name')->fetchAll();
    public_head('Lojas · ' . site_name()); echo '<section class="wrap sec"><h1>Lojas</h1>' . ($stores ? '<div class="stores">' : '<p class="empty">Nenhuma loja ainda.</p>');
    foreach ($stores as $s) echo '<a class="store" href="/loja/' . e($s['slug']) . '" style="--sc:' . e($s['color']) . '">' . ($s['logo'] ? '<img src="' . e($s['logo']) . '" alt="" loading="lazy" width="64" height="64">' : '<span class="store-ph">' . e(mb_strtoupper(mb_substr($s['name'], 0, 1))) . '</span>') . '<strong>' . e($s['name']) . '</strong><small>' . (int)$s['n'] . ' produtos</small></a>';
    echo ($stores ? '</div>' : '') . '</section>'; public_foot();
}
function page_store(string $slug): void {
    $q = db()->prepare('SELECT * FROM stores WHERE slug=? AND active=1'); $q->execute([$slug]); $s = $q->fetch(); if (!$s) { page_soon(); return; }
    page_products('Produtos ' . $s['name'], null, (int)$s['id'], coupons_store_html((int)$s['id']));
}
function page_sitemap(): void {
    $base = ((!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $u = ['/', '/produtos', '/categorias', '/lojas', '/cupons', '/contato', '/faq', '/termos', '/privacidade'];
    foreach (db()->query("SELECT slug FROM products WHERE status='published'")->fetchAll(PDO::FETCH_COLUMN) as $s) $u[] = '/produto/' . $s;
    foreach (db()->query('SELECT slug FROM categories')->fetchAll(PDO::FETCH_COLUMN) as $s) $u[] = '/categoria/' . $s;
    foreach (db()->query('SELECT slug FROM stores WHERE active=1')->fetchAll(PDO::FETCH_COLUMN) as $s) $u[] = '/loja/' . $s;
    header('Content-Type: application/xml; charset=utf-8'); echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($u as $p) echo '<url><loc>' . e($base . $p) . '</loc></url>'; echo '</urlset>';
}
function page_robots(): void {
    $base = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    header('Content-Type: text/plain; charset=utf-8'); echo "User-agent: *\nDisallow: /admin\nDisallow: /ir/\nDisallow: /install\nSitemap: $base/sitemap.xml\n";
}
function page_admin_messages(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check(); $id = (int)($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete') db()->prepare('DELETE FROM messages WHERE id=?')->execute([$id]); else db()->prepare('UPDATE messages SET is_read=1-is_read WHERE id=?')->execute([$id]);
        redirect('/admin/mensagens');
    }
    admin_head('Mensagens', 'mensagens'); $rows = db()->query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll();
    if (!$rows) echo '<p class="empty">Nenhuma mensagem ainda.</p>';
    foreach ($rows as $m) echo '<details class="card msg' . ($m['is_read'] ? '' : ' new') . '"><summary><strong>' . e($m['name']) . '</strong> · ' . e($m['email']) . ' <small>' . e($m['created_at']) . '</small></summary><p>' . nl2br(e($m['body'])) . '</p>' . ($m['whatsapp'] ? '<p class="muted">WhatsApp: ' . e($m['whatsapp']) . '</p>' : '')
        . '<div class="act"><form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$m['id'] . '"><button class="btn sm" type="submit">' . ($m['is_read'] ? 'Marcar como não lida' : 'Marcar como lida') . '</button></form><form method="post" data-confirm="Excluir esta mensagem?">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$m['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></div></details>';
    admin_foot();
}
function page_admin_pages(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        foreach (PAGE_DEFAULTS as $s => [$t]) db()->prepare('INSERT INTO pages(slug,title,content) VALUES(?,?,?) ON CONFLICT(slug) DO UPDATE SET content=excluded.content')->execute([$s, $t, mb_substr(strip_tags((string)($_POST[$s] ?? ''), PAGE_TAGS), 0, 20000)]);
        flash('Páginas salvas.'); redirect('/admin/paginas');
    }
    admin_head('Páginas (FAQ, Termos, Privacidade)', 'paginas'); echo '<form class="form" method="post">' . csrf_field();
    foreach (PAGE_DEFAULTS as $s => [$t]) echo '<div class="card-form form"><label>' . e($t) . ' (aceita &lt;p&gt;, &lt;h3&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;b&gt;, &lt;a&gt;)<textarea name="' . $s . '" rows="8">' . e(page_row($s)['content']) . '</textarea></label></div>';
    echo '<button class="btn" type="submit">Salvar páginas</button></form>'; admin_foot();
}
