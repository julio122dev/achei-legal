<?php
function page_install(): void {
    if (installed()) redirect('/');
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $email = trim((string)($_POST['email'] ?? '')); $p1 = (string)($_POST['pass'] ?? ''); $p2 = (string)($_POST['pass2'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'E-mail inválido.';
        elseif (strlen($p1) < 8) $err = 'A senha precisa ter pelo menos 8 caracteres.';
        elseif ($p1 !== $p2) $err = 'As senhas não conferem.';
        else {
            create_schema(db());
            db()->prepare('INSERT INTO admin_users(email,password_hash) VALUES(?,?)')->execute([$email, password_hash($p1, PASSWORD_DEFAULT)]);
            foreach (['site_name' => 'Achei Legal', 'logo' => '/assets/img/logo.png', 'favicon' => '/assets/img/favicon.png', 'logo_width' => '64', 'instagram' => 'https://www.instagram.com/achei.legal4/'] as $k => $v) set_setting($k, $v);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)db()->lastInsertId();
            flash('Instalação concluída. Cadastre as plataformas (lojas) de afiliados.');
            redirect('/admin/lojas');
        }
    }
    head_html('Instalação', 'auth');
    echo '<main class="auth-box"><img class="auth-logo" src="/assets/img/logo.png" alt=""><h1>Instalação</h1><p class="muted">Crie o acesso do administrador.</p>';
    if ($err) echo '<div class="flash err" role="alert">' . e($err) . '</div>';
    echo '<form method="post" class="form">' . csrf_field()
       . '<label>E-mail<input type="email" name="email" required autocomplete="username" value="' . e($_POST['email'] ?? '') . '"></label>'
       . '<label>Senha (mín. 8 caracteres)<input type="password" name="pass" required minlength="8" autocomplete="new-password"></label>'
       . '<label>Repita a senha<input type="password" name="pass2" required minlength="8" autocomplete="new-password"></label>'
       . '<button class="btn" type="submit">Instalar</button></form></main></body></html>';
}

function page_login(): void {
    global $CFG;
    if (is_admin()) redirect('/admin');
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $h = client_hash(); $since = time() - $CFG['login_window'];
        $n = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash=? AND ts>?'); $n->execute([$h, $since]);
        if ((int)$n->fetchColumn() >= $CFG['login_max_tries']) $err = 'Muitas tentativas. Aguarde alguns minutos.';
        else {
            $q = db()->prepare('SELECT id,password_hash FROM admin_users WHERE email=?'); $q->execute([trim((string)($_POST['email'] ?? ''))]);
            $u = $q->fetch();
            if ($u && password_verify((string)($_POST['pass'] ?? ''), $u['password_hash'])) {
                db()->prepare('DELETE FROM login_attempts WHERE ip_hash=?')->execute([$h]);
                session_regenerate_id(true); $_SESSION['admin_id'] = (int)$u['id']; redirect('/admin');
            }
            db()->prepare('INSERT INTO login_attempts(ip_hash,ts) VALUES(?,?)')->execute([$h, time()]);
            $err = 'E-mail ou senha incorretos.';
        }
    }
    head_html('Entrar', 'auth');
    echo '<main class="auth-box"><img class="auth-logo" src="' . e(setting('logo', '/assets/img/logo.png')) . '" alt=""><h1>Área do administrador</h1>';
    if ($err) echo '<div class="flash err" role="alert">' . e($err) . '</div>';
    echo '<form method="post" class="form">' . csrf_field()
       . '<label>E-mail<input type="email" name="email" required autocomplete="username"></label>'
       . '<label>Senha<input type="password" name="pass" required autocomplete="current-password"></label>'
       . '<button class="btn" type="submit">Entrar</button></form><p class="muted"><a href="/">← Voltar ao site</a></p></main></body></html>';
}

function page_logout(): void {
    csrf_check(); $_SESSION = []; session_destroy(); redirect('/admin/login');
}

function page_soon(): void {
    http_response_code(404);
    public_head('Em breve · ' . site_name());
    echo '<section class="wrap sec center"><h1>Em breve</h1><p class="muted">Esta página ainda está sendo construída.</p><a class="btn" href="/">Voltar ao início</a></section>';
    public_foot();
}

function page_admin_dashboard(): void {
    require_admin();
    $c = fn(string $t, string $w = '1') => (int)db()->query("SELECT COUNT(*) FROM $t WHERE $w")->fetchColumn();
    admin_head('Painel', '');
    echo '<div class="cards">';
    foreach ([['Produtos', $c('products')], ['Categorias', $c('categories')], ['Lojas', $c('stores')], ['Banners', $c('banners')], ['Mensagens novas', $c('messages', 'is_read=0')]] as [$n, $v])
        echo '<div class="card"><span>' . $n . '</span><strong>' . $v . '</strong></div>';
    echo '</div>';
    $v = visit_stats(); $clk7 = $c('click_log', "created_at>datetime('now','-7 days')"); $cp = (int)db()->query('SELECT COALESCE(SUM(clicks),0) FROM coupons')->fetchColumn();
    echo '<h2>Visitas ao site</h2><div class="cards">';
    foreach ([['Visitantes hoje', $v['today']], ['Últimos 7 dias', $v['d7']], ['Últimos 30 dias', $v['d30']], ['Total de visitantes', $v['total']], ['Páginas vistas hoje', $v['pv_today']], ['Cliques em produtos (7 dias)', $clk7], ['Cliques em cupons (total)', $cp]] as [$n, $x])
        echo '<div class="card"><span>' . $n . '</span><strong>' . (int)$x . '</strong></div>';
    echo '</div><p class="muted">Cada pessoa conta 1 vez por dia (por aparelho). Você, logado no painel, e os robôs de busca não entram na conta. A contagem começa na data em que você atualiza o site.</p>';
    $top = db()->query("SELECT p.title, COUNT(*) n FROM click_log c JOIN products p ON p.id=c.product_id WHERE c.created_at>datetime('now','-30 days') GROUP BY p.id ORDER BY n DESC LIMIT 5")->fetchAll();
    if ($top) { echo '<h2>Mais clicados (30 dias)</h2><div class="table-wrap"><table class="tbl"><tbody>'; foreach ($top as $t) echo '<tr><td>' . e($t['title']) . '</td><td>' . (int)$t['n'] . ' cliques</td></tr>'; echo '</tbody></table></div>'; }
    echo '<p class="muted">Comece cadastrando as plataformas de afiliados em <a href="/admin/lojas">Lojas</a>.</p>';
    admin_foot();
}

function page_admin_stores(): void {
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        try {
            if ($a === 'add') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 60) throw new RuntimeException('Informe o nome da loja (até 60 caracteres).');
                $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#F55879';
                $logo = upload_image($_FILES['logo'] ?? [], 'stores', 300);
                db()->prepare('INSERT INTO stores(name,slug,logo,color,sort) VALUES(?,?,?,?,?)')
                    ->execute([$name, unique_slug('stores', $name), $logo, $color, (int)($_POST['sort'] ?? 0)]);
                flash('Loja adicionada.');
            } elseif ($a === 'toggle') {
                db()->prepare('UPDATE stores SET active=1-active WHERE id=?')->execute([$id]); flash('Status atualizado.');
            } elseif ($a === 'delete') {
                $q = db()->prepare('SELECT logo FROM stores WHERE id=?'); $q->execute([$id]); $logo = $q->fetchColumn();
                db()->prepare('DELETE FROM stores WHERE id=?')->execute([$id]);   // apaga só os links dessa loja (CASCADE)
                if ($logo && str_starts_with($logo, '/uploads/stores/')) @unlink(ROOT . '/public' . $logo);
                flash('Loja removida. Os produtos continuam no site.');
            }
        } catch (Throwable $x) { flash($x instanceof RuntimeException ? $x->getMessage() : 'Erro ao salvar.', 'err'); }
        redirect('/admin/lojas');
    }
    $rows = db()->query('SELECT s.*, (SELECT COUNT(*) FROM affiliate_links l WHERE l.store_id=s.id) AS n FROM stores s ORDER BY sort,name')->fetchAll();
    admin_head('Lojas (plataformas de afiliados)', 'lojas');
    echo '<form class="form card-form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="add">' . csrf_field()
       . '<h2>Adicionar loja</h2><div class="grid2"><label>Nome<input name="name" required maxlength="60" placeholder="Ex.: Shopee"></label>'
       . '<label>Cor da marca<input type="color" name="color" value="#F55879"></label>'
       . '<label>Ordem<input type="number" name="sort" value="0"></label>'
       . '<label>Logo (JPG, PNG ou WebP, até 2 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label></div>'
       . '<button class="btn" type="submit">Adicionar loja</button></form>';
    echo '<div class="table-wrap"><table class="tbl"><thead><tr><th>Logo</th><th>Nome</th><th>Produtos</th><th>Ativa</th><th></th></tr></thead><tbody>';
    if (!$rows) echo '<tr><td colspan="5" class="empty">Nenhuma loja ainda.</td></tr>';
    foreach ($rows as $s) {
        echo '<tr><td>' . ($s['logo'] ? '<img class="thumb" src="' . e($s['logo']) . '" alt="">' : '<span class="thumb ph" style="background:' . e($s['color']) . '">' . e(mb_strtoupper(mb_substr($s['name'], 0, 1))) . '</span>') . '</td>'
           . '<td><strong>' . e($s['name']) . '</strong><br><small class="muted">/loja/' . e($s['slug']) . '</small></td><td>' . (int)$s['n'] . '</td>'
           . '<td><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int)$s['id'] . '"><button class="pill ' . ($s['active'] ? 'on' : '') . '" type="submit">' . ($s['active'] ? 'Ativa' : 'Inativa') . '</button></form></td>'
           . '<td class="act"><a class="btn light sm" href="/admin/lojas/' . (int)$s['id'] . '">Editar</a><form method="post" data-confirm="Remover ' . e($s['name']) . '? Serão apagados ' . (int)$s['n'] . ' link(s) de afiliado dessa loja.">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$s['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></td></tr>';
    }
    echo '</tbody></table></div>';
    admin_foot();
}
