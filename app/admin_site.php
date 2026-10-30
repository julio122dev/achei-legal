<?php
const GRAD_DIRS = ['90deg' => '→ Esquerda p/ direita', '270deg' => '← Direita p/ esquerda', '180deg' => '↓ Cima p/ baixo', '0deg' => '↑ Baixo p/ cima', '135deg' => '↘ Diagonal', '45deg' => '↗ Diagonal inversa'];
function safe_url(string $u): string { $u = trim($u); return (preg_match('#^https?://#i', $u) || (str_starts_with($u, '/') && !str_starts_with($u, '//'))) ? mb_substr($u, 0, 1500) : ''; }
/** Banner clicável: conta o clique e manda para o link (afiliado ou interno). */
function page_banner_go(int $id): void {
    $q = db()->prepare('SELECT link FROM banners WHERE id=?'); $q->execute([$id]); $u = safe_url((string)$q->fetchColumn());
    if ($u === '') { redirect('/'); }
    db()->prepare('UPDATE banners SET clicks=clicks+1 WHERE id=?')->execute([$id]);
    header('X-Robots-Tag: noindex'); header('Location: ' . $u, true, 302); exit;
}
function rgba(string $hex, float $o): string { return sprintf('rgba(%d,%d,%d,%.2f)', hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)), max(0, min(1, $o))); }

function banner_carousel(): void {
    if (setting('show_banner', '1') !== '1') return;
    $rows = db()->query('SELECT * FROM banners WHERE active=1 ORDER BY sort,id')->fetchAll(); if (!$rows) return;
    echo '<section class="bn" data-bn aria-roledescription="carrossel" aria-label="Destaques"><div class="bn-track">';
    foreach ($rows as $b) {
        if ($b['mode'] === 'image' && $b['img_desktop']) {
            $img = '<picture>' . ($b['img_mobile'] ? '<source media="(max-width:767px)" srcset="' . e($b['img_mobile']) . '">' : '') . '<img src="' . e($b['img_desktop']) . '" alt="' . e($b['title'] ?: 'Banner') . '" width="1200" height="400"></picture>';
            $ext = preg_match('#^https?://#i', (string)$b['link']);
            echo '<div class="bn-s">' . (safe_url((string)$b['link']) !== '' ? '<a href="/banner/' . (int)$b['id'] . '"' . ($ext ? ' target="_blank" rel="nofollow sponsored noopener"' : '') . '>' . $img . '</a>' : $img) . '</div>';
        } else {
            $c1 = rgba($b['color1'] ?: '#F55879', (float)$b['op1']); $c2 = rgba($b['color2'] ?: '#D55674', (float)$b['op2']);
            $bg = $b['grad_type'] === 'radial' ? "radial-gradient(circle at 70% 40%,$c1,$c2)" : 'linear-gradient(' . (isset(GRAD_DIRS[$b['grad_dir']]) ? $b['grad_dir'] : '135deg') . ",$c1,$c2)";
            echo '<div class="bn-s bn-c" style="background:' . e($bg) . '"><div class="bn-t"><h2>' . e($b['title']) . '</h2>' . ($b['subtitle'] ? '<p>' . e($b['subtitle']) . '</p>' : '')
               . ($b['btn_text'] && $b['btn_url'] ? '<a class="btn light" href="' . e($b['btn_url']) . '">' . e($b['btn_text']) . '</a>' : '') . '</div></div>';
        }
    }
    echo '</div>';
    if (count($rows) > 1) { echo '<button class="bn-a prev" type="button" aria-label="Anterior">‹</button><button class="bn-a next" type="button" aria-label="Próximo">›</button><div class="bn-d">'; foreach ($rows as $i => $_) echo '<button type="button" aria-label="Banner ' . ($i + 1) . '"></button>'; echo '</div>'; }
    echo '</section>';
}
function page_home(): void {
    public_head(site_name() . ' · Achadinhos com os melhores preços');
    banner_carousel();
    if (!db()->query('SELECT 1 FROM banners WHERE active=1')->fetch() || setting('show_banner', '1') !== '1') echo '<section class="hero"><div class="wrap"><h1>Achou, comprou, economizou!</h1><p>Compare preços entre as lojas e vá direto para a melhor oferta.</p><a class="btn light" href="/produtos">Ver achadinhos</a></div></section>';
    socials_section();
    if (setting('show_stores', '1') === '1') {
        $stores = db()->query('SELECT s.*, (SELECT COUNT(DISTINCT product_id) FROM affiliate_links l WHERE l.store_id=s.id) AS n FROM stores s WHERE active=1 ORDER BY sort,name')->fetchAll();
        echo '<section class="wrap sec"><div class="sec-hd"><h2>Lojas parceiras</h2><a href="/lojas">Ver todas</a></div>';
        if (!$stores) echo '<p class="empty">Nenhuma loja cadastrada ainda.</p>'; else {
            echo '<div class="stores">';
            foreach ($stores as $s) echo '<a class="store" href="/loja/' . e($s['slug']) . '" style="--sc:' . e($s['color']) . '">' . ($s['logo'] ? '<img src="' . e($s['logo']) . '" alt="" loading="lazy" width="64" height="64">' : '<span class="store-ph">' . e(mb_strtoupper(mb_substr($s['name'], 0, 1))) . '</span>') . '<strong>' . e($s['name']) . '</strong><small>' . (int)$s['n'] . ' produto' . ((int)$s['n'] === 1 ? '' : 's') . '</small></a>';
            echo '</div>';
        }
        echo '</section>';
    }
    home_products();
    public_foot();
}
function page_admin_banners(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check(); $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        try {
            if ($a === 'add') {
                $mode = ($_POST['mode'] ?? '') === 'content' ? 'content' : 'image';
                $d = upload_image($_FILES['img_desktop'] ?? [], 'banners', 1200, 400); $m = upload_image($_FILES['img_mobile'] ?? [], 'banners', 600, 400);
                if ($mode === 'image' && !$d) throw new RuntimeException('No modo "apenas imagem", envie a imagem desktop (1200x400).');
                if ($mode === 'content' && trim((string)($_POST['title'] ?? '')) === '') throw new RuntimeException('No modo "conteúdo", informe o título.');
                $hex = fn($k, $def) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : $def;
                db()->prepare('INSERT INTO banners(mode,title,subtitle,btn_text,btn_url,img_desktop,img_mobile,grad_type,grad_dir,color1,color2,op1,op2,link,sort) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
                    $mode, mb_substr(trim((string)($_POST['title'] ?? '')), 0, 100), mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 200), mb_substr(trim((string)($_POST['btn_text'] ?? '')), 0, 30), safe_url((string)($_POST['btn_url'] ?? '')),
                    $d, $m, ($_POST['grad_type'] ?? '') === 'radial' ? 'radial' : 'linear', isset(GRAD_DIRS[$_POST['grad_dir'] ?? '']) ? $_POST['grad_dir'] : '135deg', $hex('color1', '#F55879'), $hex('color2', '#D55674'),
                    max(0, min(100, (int)($_POST['op1'] ?? 100))) / 100, max(0, min(100, (int)($_POST['op2'] ?? 100))) / 100, safe_url((string)($_POST['link'] ?? '')), (int)($_POST['sort'] ?? 0)]);
                flash('Banner adicionado.');
            } elseif ($a === 'toggle') { db()->prepare('UPDATE banners SET active=1-active WHERE id=?')->execute([$id]); flash('Status atualizado.'); }
            elseif ($a === 'setlink') {
                $raw = trim((string)($_POST['link'] ?? '')); $u = safe_url($raw);
                if ($raw !== '' && $u === '') throw new RuntimeException('O link precisa começar com https:// (ou / para páginas do próprio site).');
                db()->prepare('UPDATE banners SET link=? WHERE id=?')->execute([$u, $id]); flash($u === '' ? 'Link removido: este banner não é mais clicável.' : 'Link salvo: o banner agora é clicável.');
            }
            elseif ($a === 'only') { db()->exec('UPDATE banners SET active=0'); db()->prepare('UPDATE banners SET active=1 WHERE id=?')->execute([$id]); flash('Pronto: só este banner está no ar agora.'); }
            elseif ($a === 'delete') {
                $q = db()->prepare('SELECT img_desktop,img_mobile FROM banners WHERE id=?'); $q->execute([$id]);
                foreach ($q->fetch() ?: [] as $f) if ($f && str_starts_with($f, '/uploads/banners/')) @unlink(ROOT . '/public' . $f);
                db()->prepare('DELETE FROM banners WHERE id=?')->execute([$id]); flash('Banner removido.');
            }
        } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); }
        redirect('/admin/banners');
    }
    $rows = db()->query('SELECT * FROM banners ORDER BY sort,id')->fetchAll();
    admin_head('Banners', 'banners'); $on = (int)db()->query('SELECT COUNT(*) FROM banners WHERE active=1')->fetchColumn();
    echo '<div class="flash">No ar agora: <strong>' . $on . ' banner' . ($on === 1 ? '' : 's') . '</strong>. ' . ($on > 1 ? 'Como há mais de um, eles passam em sequência na página inicial (a cada 5 segundos), na ordem do campo Ordem.' : ($on === 1 ? 'É o único que aparece na página inicial.' : 'Nenhum: a página inicial mostra o texto padrão.')) . ' Use "Deixar só este no ar" para trocar de tema de uma vez.</div>';
    $dirs = ''; foreach (GRAD_DIRS as $k => $v) $dirs .= '<option value="' . $k . '"' . ($k === '135deg' ? ' selected' : '') . '>' . $v . '</option>';
    echo '<form class="form card-form" method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="add"><h2>Novo banner</h2>'
       . '<label>Modo<select name="mode"><option value="image">Apenas imagem</option><option value="content">Conteúdo com gradiente</option></select></label>'
       . '<div class="grid2"><label>Imagem desktop (1200x400)<input type="file" name="img_desktop" accept="image/png,image/jpeg,image/webp"></label><label>Imagem mobile (600x400, opcional)<input type="file" name="img_mobile" accept="image/png,image/jpeg,image/webp"></label>'
       . '<label>Link de afiliado do banner (opcional: vazio = banner não clicável)<input name="link" maxlength="1500" placeholder="Cole aqui o link de afiliado (https://...)"></label><label>Ordem<input type="number" name="sort" value="0"></label></div>'
       . '<h2>Modo conteúdo</h2><div class="grid2"><label>Título<input name="title" maxlength="100"></label><label>Subtítulo<input name="subtitle" maxlength="200"></label><label>Texto do botão<input name="btn_text" maxlength="30"></label><label>Link do botão<input name="btn_url" placeholder="/produtos"></label>'
       . '<label>Tipo de gradiente<select name="grad_type"><option value="linear">Linear</option><option value="radial">Radial</option></select></label><label>Direção (linear)<select name="grad_dir">' . $dirs . '</select></label>'
       . '<label>Cor 1<input type="color" name="color1" value="#F55879"></label><label>Cor 2<input type="color" name="color2" value="#D55674"></label>'
       . '<label>Opacidade da cor 1: <output>100</output>%<input type="range" name="op1" min="0" max="100" value="100" data-out></label><label>Opacidade da cor 2: <output>100</output>%<input type="range" name="op2" min="0" max="100" value="100" data-out></label></div>'
       . '<button class="btn" type="submit">Adicionar banner</button></form><div class="table-wrap"><table class="tbl"><thead><tr><th>Prévia</th><th>Banner</th><th>Ativo</th><th></th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="4" class="empty">Nenhum banner. Sem banners, a home mostra o texto padrão.</td></tr>';
    foreach ($rows as $b) echo '<tr><td>' . ($b['img_desktop'] ? '<img class="thumb wide" src="' . e($b['img_desktop']) . '" alt="">' : '<span class="thumb wide" style="background:linear-gradient(135deg,' . e($b['color1']) . ',' . e($b['color2']) . ')"></span>') . '</td><td><strong>' . e($b['title'] ?: 'Banner de imagem') . '</strong><br><small class="muted">' . ($b['mode'] === 'image' ? 'Imagem' : 'Conteúdo') . ' · ordem ' . (int)$b['sort'] . ' · ' . (int)($b['clicks'] ?? 0) . ' cliques</small>'
       . ($b['mode'] === 'image' ? '<form method="post" class="inl">' . csrf_field() . '<input type="hidden" name="action" value="setlink"><input type="hidden" name="id" value="' . (int)$b['id'] . '"><input name="link" maxlength="1500" value="' . e((string)$b['link']) . '" placeholder="Link de afiliado (vazio = não clicável)"><button class="btn light sm" type="submit">Salvar link</button></form>' : '') . '</td>'
        . '<td><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int)$b['id'] . '"><button class="pill ' . ($b['active'] ? 'on' : '') . '" type="submit">' . ($b['active'] ? 'No ar' : 'Oculto') . '</button></form><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="only"><input type="hidden" name="id" value="' . (int)$b['id'] . '"><button class="btn light sm" type="submit">Deixar só este no ar</button></form></td>'
        . '<td class="act"><a class="btn sm" href="/admin/banners/' . (int)$b['id'] . '">Editar</a><form method="post" data-confirm="Excluir este banner?">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$b['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></td></tr>';
    echo '</tbody></table></div>'; admin_foot();
}
const SET_GROUPS = [
    'Cores do tema' => ['color_brand' => ['Cor da marca (cabeçalho, detalhes)', 'color'], 'color_brand_dark' => ['Cor da marca escura (hover)', 'color'], 'color_btn' => ['Botões', 'color'], 'color_soft' => ['Fundo suave', 'color'], 'color_bg' => ['Fundo da página', 'color'], 'color_text' => ['Texto', 'color'], 'color_price' => ['Preço promocional', 'color'], 'color_off' => ['Selo de desconto', 'color']],
    'Exibição' => ['sticky_header' => ['Cabeçalho fixo ao rolar', 'toggle'], 'show_products_menu' => ['Botão "Produtos" no menu', 'toggle'], 'show_blog_menu' => ['Botão "Blog" no menu', 'toggle'], 'show_banner' => ['Banner da home', 'toggle'], 'show_featured' => ['Destaques da semana', 'toggle'], 'show_latest' => ['Últimos achadinhos', 'toggle'], 'show_stores' => ['Lojas parceiras', 'toggle'], 'show_socials' => ['Redes sociais abaixo do banner', 'toggle'], 'header_pattern' => ['Fundo clarinho com desenhos no cabeçalho', 'toggle'], 'show_header_art' => ['Desenhos nas pontas do cabeçalho (telas largas)', 'toggle'], 'show_deco' => ['Desenhos nas laterais da página (desligue para deixar o espaço livre para anúncios no futuro)', 'toggle'], 'featured_auto' => ['Destaques da semana AUTOMÁTICOS (os mais clicados e vistos nos últimos 7 dias). Desligado = manual, marcando "Destaque da semana" em cada produto', 'toggle']],
    'Contato e redes' => ['contact_email' => ['E-mail', 'text'], 'contact_whatsapp' => ['WhatsApp (com DDD)', 'text'], 'contact_hours' => ['Horário de atendimento', 'text'], 'instagram' => ['Instagram (URL)', 'url'], 'facebook' => ['Facebook (URL)', 'url'], 'tiktok' => ['TikTok (URL)', 'url'], 'whatsapp_group' => ['Grupo do WhatsApp (URL)', 'url'], 'telegram_group' => ['Grupo do Telegram (URL)', 'url']],
    'Integrações' => ['shopee_app_id' => ['Shopee: App ID (Open API de afiliados)', 'text'], 'shopee_app_secret' => ['Shopee: App Secret (deixe em branco para manter o atual)', 'password']],
    'Textos' => ['footer_text' => ['Texto do rodapé', 'textarea'], 'legal' => ['Aviso legal / afiliados (página do produto)', 'textarea']],
];
function page_admin_settings(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        try {
            set_setting('site_name', mb_substr(trim((string)($_POST['site_name'] ?? '')), 0, 60)); set_setting('logo_width', (string)max(20, min(120, (int)($_POST['logo_width'] ?? 64))));
            if ($l = upload_image($_FILES['logo'] ?? [], 'site', 512)) set_setting('logo', $l);
            if ($f = upload_image($_FILES['favicon'] ?? [], 'site', 192)) set_setting('favicon', $f);
            foreach (SET_GROUPS as $fields) foreach ($fields as $k => [$label, $type]) {
                $v = (string)($_POST[$k] ?? '');
                if ($type === 'toggle') $v = isset($_POST[$k]) ? '1' : '0';
                elseif ($type === 'password') { if ($v === '') continue; $v = mb_substr(trim($v), 0, 200); }
                elseif ($type === 'color') { if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) continue; }
                elseif ($type === 'url') { $v = trim($v); if ($v !== '' && !preg_match('#^https?://#i', $v)) throw new RuntimeException("O campo \"$label\" precisa começar com http:// ou https://"); $v = mb_substr($v, 0, 300); }
                else $v = mb_substr(trim($v), 0, $type === 'textarea' ? 600 : 120);
                set_setting($k, $v);
            }
            flash('Configurações salvas.');
        } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); }
        redirect('/admin/configuracoes');
    }
    admin_head('Configurações', 'config');
    echo '<form class="form" method="post" enctype="multipart/form-data">' . csrf_field() . '<div class="card-form form"><h2>Geral</h2><div class="grid2"><label>Nome do site<input name="site_name" maxlength="60" value="' . e(setting('site_name')) . '"></label>'
       . '<label>Tamanho da logo: <output>' . (int)setting('logo_width', '64') . '</output>px<input type="range" name="logo_width" min="20" max="120" value="' . (int)setting('logo_width', '64') . '" data-out></label>'
       . '<label>Trocar logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label><label>Trocar favicon<input type="file" name="favicon" accept="image/png,image/jpeg,image/webp"></label></div><img class="thumb" src="' . e(setting('logo', '/assets/img/logo.png')) . '" alt="Logo atual"></div>';
    foreach (SET_GROUPS as $g => $fields) {
        echo '<div class="card-form form"><h2>' . e($g) . '</h2><div class="grid2">';
        foreach ($fields as $k => [$label, $type]) {
            if ($type === 'password') { echo '<label>' . e($label) . '<input name="' . $k . '" type="password" autocomplete="new-password" placeholder="' . (setting($k) !== '' ? '(já salvo)' : '') . '"></label>'; continue; }
            $v = setting($k, $type === 'color' ? THEME[substr($k, 6)] : ($type === 'toggle' ? (in_array($k, ['show_blog_menu', 'featured_auto'], true) ? '0' : '1') : ''));
            if ($type === 'toggle') echo '<label class="chk"><input type="checkbox" name="' . $k . '" value="1"' . ($v === '1' ? ' checked' : '') . '> ' . e($label) . '</label>';
            elseif ($type === 'textarea') echo '<label>' . e($label) . '<textarea name="' . $k . '" rows="3" maxlength="600">' . e($v) . '</textarea></label>';
            else echo '<label>' . e($label) . '<input name="' . $k . '" type="' . ($type === 'color' ? 'color' : 'text') . '" value="' . e($v) . '"></label>';
        }
        echo '</div></div>';
    }
    echo '<button class="btn" type="submit">Salvar configurações</button></form><div class="card-form form"><h2>DevTools</h2><form method="post" action="/admin/dev">' . csrf_field() . '<input type="hidden" name="action" value="seed"><button class="btn light" type="submit">Executar Seed (dados de teste)</button></form>'
       . '<form class="form" method="post" action="/admin/dev" data-confirm="Apagar TODOS os produtos, categorias, lojas, banners e mensagens?">' . csrf_field() . '<input type="hidden" name="action" value="clear"><label>Digite LIMPAR para confirmar<input name="confirm" autocomplete="off"></label><label class="chk"><input type="checkbox" name="sure" value="1"> Tenho certeza</label><button class="btn danger" type="submit">Limpar todos os dados</button></form></div>'; admin_foot();
}
function page_admin_store_edit(int $id): void {
    require_admin(); $q = db()->prepare('SELECT * FROM stores WHERE id=?'); $q->execute([$id]); $s = $q->fetch(); if (!$s) redirect('/admin/lojas');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        try {
            $name = trim((string)($_POST['name'] ?? '')); if ($name === '' || mb_strlen($name) > 60) throw new RuntimeException('Informe o nome da loja (até 60 caracteres).');
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? $_POST['color'] : $s['color']; $logo = $s['logo'];
            if ($n = upload_image($_FILES['logo'] ?? [], 'stores', 300)) { if ($logo && str_starts_with($logo, '/uploads/stores/')) @unlink(ROOT . '/public' . $logo); $logo = $n; }
            db()->prepare('UPDATE stores SET name=?,color=?,sort=?,active=?,logo=? WHERE id=?')->execute([$name, $color, (int)($_POST['sort'] ?? 0), empty($_POST['active']) ? 0 : 1, $logo, $id]);
            flash('Loja atualizada.'); redirect('/admin/lojas');
        } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); $s = array_merge($s, $_POST); }
    }
    admin_head('Editar loja', 'lojas');
    echo '<form class="form card-form" method="post" enctype="multipart/form-data">' . csrf_field() . '<div class="grid2"><label>Nome<input name="name" required maxlength="60" value="' . e($s['name']) . '"></label><label>Cor da marca<input type="color" name="color" value="' . e($s['color'] ?: '#F55879') . '"></label>'
       . '<label>Ordem<input type="number" name="sort" value="' . (int)$s['sort'] . '"></label><label class="chk"><input type="checkbox" name="active" value="1"' . (!empty($s['active']) ? ' checked' : '') . '> Loja ativa (aparece no site)</label></div>'
       . ($s['logo'] ? '<p>Logo atual:<br><img class="thumb" src="' . e($s['logo']) . '" alt=""></p>' : '') . '<label>Trocar logo (opcional; JPG, PNG ou WebP até 8 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>'
       . '<p class="muted">O endereço da loja (/loja/' . e($s['slug']) . ') não muda, mesmo se você trocar o nome.</p><div class="act"><button class="btn" type="submit">Salvar</button><a class="btn light" href="/admin/lojas">Cancelar</a></div></form>'; admin_foot();
}

function page_admin_banner_edit(int $id): void {
    require_admin(); $q = db()->prepare('SELECT * FROM banners WHERE id=?'); $q->execute([$id]); $b = $q->fetch(); if (!$b) redirect('/admin/banners');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        try {
            $mode = ($_POST['mode'] ?? '') === 'content' ? 'content' : 'image';
            $d = upload_image($_FILES['img_desktop'] ?? [], 'banners', 1200, 400); $m = upload_image($_FILES['img_mobile'] ?? [], 'banners', 600, 400);
            $own = fn($f) => $f && str_starts_with($f, '/uploads/banners/');
            $dsk = $b['img_desktop']; $mob = $b['img_mobile'];
            if ($d) { if ($own($dsk)) @unlink(ROOT . '/public' . $dsk); $dsk = $d; }
            if ($m) { if ($own($mob)) @unlink(ROOT . '/public' . $mob); $mob = $m; }
            if (!empty($_POST['rm_mobile']) && !$m) { if ($own($mob)) @unlink(ROOT . '/public' . $mob); $mob = null; }
            if ($mode === 'image' && !$dsk) throw new RuntimeException('No modo "apenas imagem", envie a imagem desktop (1200x400).');
            if ($mode === 'content' && trim((string)($_POST['title'] ?? '')) === '') throw new RuntimeException('No modo "conteúdo", informe o título.');
            $raw = trim((string)($_POST['link'] ?? '')); $lk = safe_url($raw); $bu = safe_url((string)($_POST['btn_url'] ?? ''));
            if (($raw !== '' && $lk === '') || (trim((string)($_POST['btn_url'] ?? '')) !== '' && $bu === '')) throw new RuntimeException('O link precisa começar com https:// (ou / para páginas do próprio site).');
            $hex = fn($k, $def) => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : $def;
            db()->prepare('UPDATE banners SET mode=?,title=?,subtitle=?,btn_text=?,btn_url=?,img_desktop=?,img_mobile=?,grad_type=?,grad_dir=?,color1=?,color2=?,op1=?,op2=?,link=?,sort=? WHERE id=?')->execute([
                $mode, mb_substr(trim((string)($_POST['title'] ?? '')), 0, 100), mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 200), mb_substr(trim((string)($_POST['btn_text'] ?? '')), 0, 30), $bu,
                $dsk, $mob, ($_POST['grad_type'] ?? '') === 'radial' ? 'radial' : 'linear', isset(GRAD_DIRS[$_POST['grad_dir'] ?? '']) ? $_POST['grad_dir'] : '135deg', $hex('color1', '#F55879'), $hex('color2', '#D55674'),
                max(0, min(100, (int)($_POST['op1'] ?? 100))) / 100, max(0, min(100, (int)($_POST['op2'] ?? 100))) / 100, $lk, (int)($_POST['sort'] ?? 0), $id]);
            flash('Banner atualizado.'); redirect('/admin/banners');
        } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); $b = array_merge($b, array_intersect_key($_POST, $b)); }
    }
    admin_head('Editar banner', 'banners');
    $dirs = ''; foreach (GRAD_DIRS as $k => $v) $dirs .= '<option value="' . $k . '"' . ($k === ($b['grad_dir'] ?: '135deg') ? ' selected' : '') . '>' . $v . '</option>';
    $v = fn($k) => e((string)($b[$k] ?? '')); $sel = fn($k, $val) => ((string)($b[$k] ?? '') === $val ? ' selected' : '');
    $pc = fn($k) => (int)round((float)($b[$k] ?? 1) * 100);
    echo '<p class="muted">Cliques neste banner: <strong>' . (int)($b['clicks'] ?? 0) . '</strong> (não zeram ao editar).</p>'
       . '<form class="form card-form" method="post" enctype="multipart/form-data">' . csrf_field()
       . '<label>Modo<select name="mode"><option value="image"' . $sel('mode', 'image') . '>Apenas imagem</option><option value="content"' . $sel('mode', 'content') . '>Conteúdo com gradiente</option></select></label>'
       . ($b['img_desktop'] ? '<p>Imagem desktop atual:<br><img class="thumb wide" src="' . e($b['img_desktop']) . '" alt=""></p>' : '')
       . ($b['img_mobile'] ? '<p>Imagem mobile atual:<br><img class="thumb wide" src="' . e($b['img_mobile']) . '" alt=""></p><label class="chk"><input type="checkbox" name="rm_mobile" value="1"> Remover a imagem mobile</label>' : '')
       . '<div class="grid2"><label>Trocar imagem desktop (1200x400; vazio = mantém)<input type="file" name="img_desktop" accept="image/png,image/jpeg,image/webp"></label><label>Trocar imagem mobile (600x400; vazio = mantém)<input type="file" name="img_mobile" accept="image/png,image/jpeg,image/webp"></label>'
       . '<label>Link de afiliado do banner (opcional: vazio = banner não clicável)<input name="link" maxlength="1500" value="' . $v('link') . '" placeholder="https://..."></label><label>Ordem<input type="number" name="sort" value="' . (int)$b['sort'] . '"></label></div>'
       . '<h2>Modo conteúdo</h2><div class="grid2"><label>Título<input name="title" maxlength="100" value="' . $v('title') . '"></label><label>Subtítulo<input name="subtitle" maxlength="200" value="' . $v('subtitle') . '"></label><label>Texto do botão<input name="btn_text" maxlength="30" value="' . $v('btn_text') . '"></label><label>Link do botão<input name="btn_url" value="' . $v('btn_url') . '" placeholder="/produtos"></label>'
       . '<label>Tipo de gradiente<select name="grad_type"><option value="linear"' . $sel('grad_type', 'linear') . '>Linear</option><option value="radial"' . $sel('grad_type', 'radial') . '>Radial</option></select></label><label>Direção (linear)<select name="grad_dir">' . $dirs . '</select></label>'
       . '<label>Cor 1<input type="color" name="color1" value="' . e($b['color1'] ?: '#F55879') . '"></label><label>Cor 2<input type="color" name="color2" value="' . e($b['color2'] ?: '#D55674') . '"></label>'
       . '<label>Opacidade da cor 1: <output>' . $pc('op1') . '</output>%<input type="range" name="op1" min="0" max="100" value="' . $pc('op1') . '" data-out></label><label>Opacidade da cor 2: <output>' . $pc('op2') . '</output>%<input type="range" name="op2" min="0" max="100" value="' . $pc('op2') . '" data-out></label></div>'
       . '<div class="act"><button class="btn" type="submit">Salvar</button><a class="btn light" href="/admin/banners">Cancelar</a></div></form>';
    admin_foot();
}
