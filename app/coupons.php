<?php
function coupon_status(array $c): string {
    $t = date('Y-m-d');
    if (!$c['active']) return 'Oculto';
    if ($c['start_at'] && $c['start_at'] > $t) return 'Agendado';
    if ($c['end_at'] && $c['end_at'] < $t) return 'Expirado';
    return 'No ar';
}
/** Cupons no ar hoje (dentro das datas). $storeId = só os dessa loja. */
function coupons_active(?int $storeId = null): array {
    $t = date('Y-m-d'); $w = $storeId === null ? '' : ' AND c.store_id=' . (int)$storeId;
    $q = db()->prepare("SELECT c.*, s.name sname, s.logo slogo, s.color scolor FROM coupons c LEFT JOIN stores s ON s.id=c.store_id WHERE c.active=1$w AND (c.start_at IS NULL OR c.start_at='' OR c.start_at<=?) AND (c.end_at IS NULL OR c.end_at='' OR c.end_at>=?) AND (c.store_id IS NULL OR s.active=1) ORDER BY (c.store_id IS NULL) DESC, s.sort, s.name, c.sort, c.id");
    $q->execute([$t, $t]); return $q->fetchAll();
}
function hex_or(?string $v, string $fb): string { return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? $v : $fb; }
/** Cor do texto (branco ou escuro) conforme o fundo. */
function text_on(string $hex): string {
    [$r, $g, $b] = sscanf($hex, '#%2x%2x%2x'); return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 170 ? '#3a2530' : '#ffffff';
}
/** Cor do quadradinho: a do cupom; se vazia, a da loja; se não, a rosa do site. */
function coupon_color(array $c): string { return hex_or($c['color'] ?? '', hex_or($c['scolor'] ?? '', '#D63F63')); }
/** Quadradinho do cupom (mesmo tamanho dos quadradinhos de loja). */
function coupon_card(array $c): string {
    $go = '/cupom/' . (int)$c['id']; $btn = e($c['btn_text'] ?: 'Pegar cupom'); $bg = coupon_color($c);
    $until = $c['end_at'] ? '<small>Até ' . e(date('d/m', strtotime($c['end_at']))) . '</small>' : '';
    $code = $c['code'] !== '' ? '<button type="button" class="cpt-code" data-copy="' . e($c['code']) . '" title="Copiar o código">' . e($c['code']) . '</button>' : '';
    $sub = $c['subtitle'] !== '' ? '<span class="cpt-s">' . e($c['subtitle']) . '</span>' : '';
    $a = '<a class="cpt-t" href="' . $go . '" target="_blank" rel="nofollow sponsored noopener">' . e($c['title']) . '</a>';
    if (!empty($c['logo'])) // imagem própria = fundo do quadradinho inteiro
        return '<div class="cpt cpt-img" style="background-image:url(\'' . e($c['logo']) . '\');--cc:' . e($bg) . '"><div class="cpt-over">' . $a . $sub . $until . $code . '<span class="cpt-btn">' . $btn . '</span></div></div>';
    $logo = $c['slogo'] ?? '';
    $img = $logo ? '<img src="' . e($logo) . '" alt="" loading="lazy" width="56" height="56">' : '<span class="cpt-ph">' . e(mb_strtoupper(mb_substr($c['sname'] ?: $c['title'], 0, 1))) . '</span>';
    return '<div class="cpt" style="--cc:' . e($bg) . ';--cf:' . text_on($bg) . '">' . $img . $a . $sub . $until . $code . '<span class="cpt-btn">' . $btn . '</span></div>';
}
/** Cupons no início da página de uma loja. */
function coupons_store_html(int $storeId): string {
    $r = array_slice(coupons_active($storeId), 0, 4);
    return $r ? '<div class="cpt-grid top" aria-label="Cupons desta loja">' . implode('', array_map('coupon_card', $r)) . '</div>' : '';
}
function page_coupons(): void {
    $rows = coupons_active(); $g = []; foreach ($rows as $c) $g[(int)($c['store_id'] ?? 0)][] = $c;
    public_head('Cupons · ' . site_name()); echo '<section class="wrap sec"><h1>Cupons</h1><p class="muted">Cupons e ofertas das lojas parceiras. Toque no quadradinho para ir à loja.</p>';
    if (!$rows) echo '<p class="empty">Nenhum cupom no ar agora. Volte em breve!</p>';
    foreach ($g as $sid => $list) {
        $f = $list[0]; echo '<div class="cp-group"><h2>' . ($sid && $f['slogo'] ? '<img src="' . e($f['slogo']) . '" alt="" width="32" height="32"> ' : '') . ($sid ? e($f['sname']) : 'Cupons gerais') . '</h2><div class="cpt-grid">';
        foreach ($list as $c) echo coupon_card($c); echo '</div></div>';
    }
    echo '</section>'; public_foot();
}
function page_coupon_go(int $id): void {
    $q = db()->prepare('SELECT url FROM coupons WHERE id=?'); $q->execute([$id]); $u = $q->fetchColumn();
    if (!$u || !preg_match('#^https?://#i', (string)$u)) { page_soon(); return; }
    db()->prepare('UPDATE coupons SET clicks=clicks+1 WHERE id=?')->execute([$id]);
    header('X-Robots-Tag: noindex'); header('Location: ' . $u, true, 302); exit;
}
/** Lê e valida os campos do formulário de cupom. Lança RuntimeException se algo estiver errado. */
function coupon_read(array $old = []): array {
    $t = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 80); $u = trim((string)($_POST['url'] ?? ''));
    $d = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : null; $s = $d('start_at'); $en = $d('end_at');
    $sid = (int)($_POST['store_id'] ?? 0); if ($sid && !db()->query('SELECT 1 FROM stores WHERE id=' . $sid)->fetch()) $sid = 0;
    if ($t === '' || !preg_match('#^https?://#i', $u) || mb_strlen($u) > 1500) throw new RuntimeException('Informe o título e um link que comece com https://');
    if ($s && $en && $en < $s) throw new RuntimeException('A data final precisa ser igual ou depois da inicial.');
    $color = empty($_POST['own_color']) ? '' : hex_or($_POST['color'] ?? '', '');
    $logo = $old['logo'] ?? '';
    if (!empty($_POST['rm_logo']) && $logo) { if (str_starts_with($logo, '/uploads/coupons/')) @unlink(ROOT . '/public' . $logo); $logo = ''; }
    if ($n = upload_image($_FILES['logo'] ?? [], 'coupons', 600)) { if ($logo && str_starts_with($logo, '/uploads/coupons/')) @unlink(ROOT . '/public' . $logo); $logo = $n; }
    return [$t, mb_substr(trim((string)($_POST['subtitle'] ?? '')), 0, 120), mb_substr(trim((string)($_POST['code'] ?? '')), 0, 40), mb_substr(trim((string)($_POST['btn_text'] ?? '')), 0, 30), $u, $sid ?: null, $s, $en, (int)($_POST['sort'] ?? 0), $color, $logo];
}
function coupon_form(array $c, string $action, string $submit): string {
    $opts = '<option value="">Cupom geral (não é de uma loja)</option>';
    foreach (db()->query('SELECT id,name FROM stores ORDER BY sort,name')->fetchAll() as $s) $opts .= '<option value="' . (int)$s['id'] . '"' . ((int)($c['store_id'] ?? 0) === (int)$s['id'] ? ' selected' : '') . '>' . e($s['name']) . '</option>';
    $own = !empty($c['color']); $v = fn($k) => e((string)($c[$k] ?? ''));
    return '<form class="form card-form" method="post" enctype="multipart/form-data">' . csrf_field() . '<input type="hidden" name="action" value="' . $action . '"><div class="grid2">'
       . '<label>Loja do cupom<select name="store_id">' . $opts . '</select></label><label>Título* (nome no quadradinho)<input name="title" required maxlength="80" value="' . $v('title') . '" placeholder="Ex.: Shopee 11.11"></label>'
       . '<label>Subtítulo (opcional)<input name="subtitle" maxlength="120" value="' . $v('subtitle') . '" placeholder="Ex.: R$10 off"></label><label>Link do cupom*<input name="url" type="url" required maxlength="1500" value="' . $v('url') . '" placeholder="https://..."></label>'
       . '<label>Texto do botão<input name="btn_text" maxlength="30" value="' . $v('btn_text') . '" placeholder="Pegar cupom"></label><label>Código (opcional)<input name="code" maxlength="40" value="' . $v('code') . '" placeholder="Só se a loja der um código"></label>'
       . '<label>Começa em (opcional)<input type="date" name="start_at" value="' . $v('start_at') . '"></label><label>Termina em (opcional)<input type="date" name="end_at" value="' . $v('end_at') . '"></label>'
       . '<label>Posição (ordem; menor aparece primeiro)<input type="number" name="sort" value="' . (int)($c['sort'] ?? 0) . '"></label>'
       . '<label>Cor de fundo (usada quando não há imagem)<input type="color" name="color" value="' . e(hex_or($c['color'] ?? '', hex_or($c['scolor'] ?? '', '#D63F63'))) . '"></label></div>'
       . '<label class="chk"><input type="checkbox" name="own_color" value="1"' . ($own ? ' checked' : '') . '> Usar esta cor (desmarcado = usa a cor da loja)</label>'
       . (!empty($c['logo']) ? '<p>Imagem atual do cupom (fundo):<br><img class="thumb" src="' . e($c['logo']) . '" alt=""></p><label class="chk"><input type="checkbox" name="rm_logo" value="1"> Remover a imagem (volta a usar a cor)</label>' : '')
       . '<label>Imagem de fundo do cupom (opcional; JPG, PNG ou WebP até 8 MB). Com imagem, ela cobre o quadradinho todo; sem imagem, vale a cor<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>'
       . '<div class="act"><button class="btn" type="submit">' . $submit . '</button>' . ($action === 'edit' ? '<a class="btn light" href="/admin/cupons">Cancelar</a>' : '') . '</div></form>';
}
function page_admin_coupon_edit(int $id): void {
    require_admin(); $q = db()->prepare('SELECT c.*, s.color scolor FROM coupons c LEFT JOIN stores s ON s.id=c.store_id WHERE c.id=?'); $q->execute([$id]); $c = $q->fetch(); if (!$c) redirect('/admin/cupons');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        try {
            $r = coupon_read($c); $r[] = $id;
            db()->prepare('UPDATE coupons SET title=?,subtitle=?,code=?,btn_text=?,url=?,store_id=?,start_at=?,end_at=?,sort=?,color=?,logo=? WHERE id=?')->execute($r);
            flash('Cupom atualizado. Os cliques foram mantidos.'); redirect('/admin/cupons');
        } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); $c = array_merge($c, $_POST, ['logo' => $c['logo']]); }
    }
    admin_head('Editar cupom', 'cupons');
    echo '<p class="muted">Cliques deste cupom: <strong>' . (int)$c['clicks'] . '</strong> (não zeram ao editar).</p>' . coupon_form($c, 'edit', 'Salvar'); admin_foot();
}
function page_admin_coupons(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check(); $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        if ($a === 'add') {
            try {
                db()->prepare('INSERT INTO coupons(title,subtitle,code,btn_text,url,store_id,start_at,end_at,sort,color,logo) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute(coupon_read()); flash('Cupom adicionado.');
            } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); }
        } elseif ($a === 'toggle') { db()->prepare('UPDATE coupons SET active=1-active WHERE id=?')->execute([$id]); flash('Status atualizado.'); }
        elseif ($a === 'delete') {
            $q = db()->prepare('SELECT logo FROM coupons WHERE id=?'); $q->execute([$id]); $lg = (string)$q->fetchColumn(); if (str_starts_with($lg, '/uploads/coupons/')) @unlink(ROOT . '/public' . $lg);
            db()->prepare('DELETE FROM coupons WHERE id=?')->execute([$id]); flash('Cupom removido.');
        }
        redirect('/admin/cupons');
    }
    admin_head('Cupons', 'cupons');
    echo '<h2>Novo cupom</h2>' . coupon_form([], 'add', 'Adicionar cupom')
       . '<p class="muted">O cupom aparece na aba <strong>Cupons</strong> e no topo da página da loja, como quadradinho. Com datas, ele entra e sai sozinho. Use <strong>Editar</strong> para mudar nome, cor, posição ou logo sem perder os cliques.</p>'
       . '<div class="table-wrap"><table class="tbl"><thead><tr><th>Cupom</th><th>Loja</th><th>Período</th><th>Situação</th><th>Cliques</th><th></th></tr></thead><tbody>';
    $rows = db()->query('SELECT c.*, s.name sname, s.color scolor FROM coupons c LEFT JOIN stores s ON s.id=c.store_id ORDER BY (c.store_id IS NULL) DESC, s.name, c.sort, c.id DESC')->fetchAll(); if (!$rows) echo '<tr><td colspan="6" class="empty">Nenhum cupom ainda.</td></tr>';
    foreach ($rows as $c) {
        $st = coupon_status($c); $per = ($c['start_at'] ?: 'sempre') . ' → ' . ($c['end_at'] ?: 'sem fim');
        echo '<tr><td><span class="dot" style="background:' . e(coupon_color($c)) . '"></span><strong>' . e($c['title']) . '</strong><br><small class="muted">' . e($c['subtitle']) . ($c['code'] !== '' ? ' · código ' . e($c['code']) : '') . ' · posição ' . (int)$c['sort'] . '</small></td><td>' . e($c['sname'] ?: 'Geral') . '</td><td><small>' . e($per) . '</small></td>'
           . '<td><span class="pill ' . ($st === 'No ar' ? 'on' : '') . '">' . $st . '</span></td><td>' . (int)$c['clicks'] . '</td><td class="act"><a class="btn sm" href="/admin/cupons/' . (int)$c['id'] . '">Editar</a><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="btn light sm" type="submit">' . ($c['active'] ? 'Ocultar' : 'Mostrar') . '</button></form>'
           . '<form method="post" data-confirm="Excluir este cupom? Os cliques serão perdidos. Prefira Editar ou Ocultar."><input type="hidden" name="action" value="delete">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></td></tr>';
    }
    echo '</tbody></table></div>'; admin_foot();
}
