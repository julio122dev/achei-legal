<?php
function gd_tile(int $px, array $c1, array $c2, string $dir, int $shape): string {
    $im = imagecreatetruecolor($px, $px);
    for ($y = 0; $y < $px; $y++) { $t = $y / $px; imageline($im, 0, $y, $px, $y, imagecolorallocate($im, (int)($c1[0] + ($c2[0] - $c1[0]) * $t), (int)($c1[1] + ($c2[1] - $c1[1]) * $t), (int)($c1[2] + ($c2[2] - $c1[2]) * $t))); }
    $w = imagecolorallocatealpha($im, 255, 255, 255, 40); $r = (int)($px * .28);
    if ($shape % 3 === 0) imagefilledellipse($im, intdiv($px, 2), intdiv($px, 2), $r * 2, $r * 2, $w);
    elseif ($shape % 3 === 1) imagefilledrectangle($im, intdiv($px, 2) - $r, intdiv($px, 2) - $r, intdiv($px, 2) + $r, intdiv($px, 2) + $r, $w);
    else imagefilledpolygon($im, [intdiv($px, 2), intdiv($px, 2) - $r, intdiv($px, 2) + $r, intdiv($px, 2) + $r, intdiv($px, 2) - $r, intdiv($px, 2) + $r], 3, $w);
    $d = ROOT . "/public/uploads/$dir"; if (!is_dir($d)) mkdir($d, 0755, true); $n = bin2hex(random_bytes(6)) . '.png'; imagepng($im, "$d/$n"); return "/uploads/$dir/$n";
}
function run_seed(): void {
    $db = db(); $db->beginTransaction();
    try {
        $cats = ['Casa e Cozinha', 'Eletrônicos', 'Beleza', 'Moda', 'Pet', 'Esporte']; $cid = [];
        foreach ($cats as $i => $c) { $db->prepare('INSERT INTO categories(name,slug,sort) VALUES(?,?,?)')->execute([$c, unique_slug('categories', $c), $i]); $cid[] = (int)$db->lastInsertId(); }
        foreach (['Utilidades', 'Cozinha'] as $i => $c) $db->prepare('INSERT INTO categories(name,slug,parent_id,sort) VALUES(?,?,?,?)')->execute([$c, unique_slug('categories', $c), $cid[0], 10 + $i]);
        $stores = [['Loja Aurora', [245, 88, 121]], ['Mega Ofertas', [37, 99, 235]], ['Super Preço', [22, 163, 74]], ['Canto Shop', [245, 158, 11]]]; $sid = [];
        foreach ($stores as $i => [$n, $c]) { $hex = sprintf('#%02X%02X%02X', ...$c); $db->prepare('INSERT INTO stores(name,slug,logo,color,sort) VALUES(?,?,?,?,?)')->execute([$n, unique_slug('stores', $n), gd_tile(300, $c, [255, 255, 255], 'seed', $i), $hex, $i]); $sid[] = (int)$db->lastInsertId(); }
        $items = ['Air Fryer 4L Digital', 'Liquidificador 900W', 'Jogo de Panelas 5 Peças', 'Fone Bluetooth', 'Smartwatch Fitness', 'Caixa de Som Portátil', 'Kit Skincare Facial', 'Secador de Cabelo 2000W', 'Escova Alisadora', 'Tênis Casual Confort', 'Mochila Impermeável', 'Relógio Minimalista', 'Cama para Cachorro G', 'Comedouro Automático', 'Brinquedo Interativo Pet', 'Garrafa Térmica 1L', 'Kit Halteres 10kg', 'Tapete de Yoga', 'Cafeteira Elétrica', 'Organizador de Gavetas', 'Ventilador de Mesa', 'Mouse Sem Fio', 'Carregador Turbo 33W', 'Luminária LED de Mesa'];
        foreach ($items as $i => $t) {
            $cat = $cid[intdiv($i, 4)]; $hue = [[245, 88, 121], [99, 102, 241], [236, 72, 153], [20, 184, 166], [249, 115, 22], [34, 197, 94]][intdiv($i, 4)];
            $db->prepare('INSERT INTO products(title,slug,short_desc,description,keywords,featured,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute([$t, unique_slug('products', $t), 'Produto de exemplo para testar o site.', '<p>Descrição de exemplo de <b>' . $t . '</b>. Substitua por dados reais.</p>', strtolower($t), $i % 5 === 0 ? 1 : 0]);
            $pid = (int)$db->lastInsertId();
            $db->prepare('UPDATE products SET sku=? WHERE id=?')->execute([strtoupper(substr(preg_replace('/[^a-z]/', '', slugify($cats[intdiv($i, 4)])), 0, 3)) . '-' . sprintf('%04d', $pid), $pid]);
            $db->prepare('INSERT INTO product_category VALUES(?,?)')->execute([$pid, $cat]);
            $db->prepare('INSERT INTO product_images(product_id,path,sort) VALUES(?,?,0)')->execute([$pid, gd_tile(600, $hue, [255, 255, 255], 'seed', $i)]);
            $base = random_int(39, 499) + 0.9; $pick = array_slice($sid, 0, random_int(2, 4));
            foreach ($pick as $k => $s) { $p = round($base * (1 + $k * 0.07), 2); $db->prepare('INSERT INTO affiliate_links(product_id,store_id,url,price,old_price,sort) VALUES(?,?,?,?,?,?)')->execute([$pid, $s, 'https://example.com/produto-' . $pid . '-' . $s, $p, ($i + $k) % 3 === 0 ? round($p * 1.3, 2) : null, $k]); }
        }
        foreach ([['Achou, comprou, economizou!', 'Compare preços e vá direto para a melhor oferta', '#F55879', '#D55674'], ['Ofertas da semana', 'Os achadinhos mais baratos de várias lojas', '#FF8FA3', '#F55879']] as $i => [$t, $s, $a, $b])
            $db->prepare("INSERT INTO banners(mode,title,subtitle,btn_text,btn_url,color1,color2,sort) VALUES('content',?,?,'Ver achadinhos','/produtos',?,?,?)")->execute([$t, $s, $a, $b, $i]);
        $db->commit();
    } catch (Throwable $x) { $db->rollBack(); throw $x; }
}
function clear_all(): void {
    foreach (['products', 'categories', 'stores', 'banners', 'messages', 'views_log', 'click_log'] as $t) db()->exec("DELETE FROM $t");
    foreach (['products', 'stores', 'banners', 'seed'] as $d) foreach (glob(ROOT . "/public/uploads/$d/*.png") ?: [] as $f) @unlink($f);
}
function page_admin_dev(): void {
    require_admin(); if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/admin/configuracoes'); csrf_check();
    try {
        if (($_POST['action'] ?? '') === 'seed') { run_seed(); flash('Dados de teste criados.'); }
        elseif (($_POST['action'] ?? '') === 'clear' && ($_POST['confirm'] ?? '') === 'LIMPAR' && !empty($_POST['sure'])) { clear_all(); flash('Todos os dados foram removidos.'); }
        else flash('Para limpar, digite LIMPAR e marque a caixa de confirmação.', 'err');
    } catch (Throwable $x) { flash('Erro: ' . $x->getMessage(), 'err'); }
    redirect('/admin/configuracoes');
}
