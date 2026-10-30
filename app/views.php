<?php
const THEME = ['brand' => '#F55879', 'brand_dark' => '#D55674', 'btn' => '#D63F63', 'soft' => '#FFF1F4', 'bg' => '#FFF8FA', 'text' => '#3A2530', 'price' => '#16A34A', 'off' => '#DC2626'];

function theme_css(): string {
    $o = ':root{';
    foreach (THEME as $k => $d) {
        $v = setting('color_' . $k, $d);
        $o .= '--' . str_replace('_', '-', $k) . ':' . (preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $d) . ';';
    }
    return $o . '--logo-w:' . max(20, min(120, (int)setting('logo_width', '64'))) . 'px}' . header_pattern_css();
}
function site_name(): string { return setting('site_name', '') ?: 'Achadinhos'; }
function head_html(string $title, string $extraClass = ''): void {
    $fav = setting('favicon', '/assets/img/favicon.png');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
       . '<title>' . e($title) . '</title><meta name="theme-color" content="' . e(setting('color_brand', THEME['brand'])) . '">'
       . '<link rel="icon" href="' . e($fav) . '">'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap">'
       . '<link rel="stylesheet" href="/assets/css/site.css?v=' . @filemtime(ROOT . '/public/assets/css/site.css') . '"><style>' . theme_css() . '</style></head><body class="' . e($extraClass) . '">';
}
function flash_html(): void {
    if ($f = flash()) echo '<div class="flash ' . e($f[1]) . '" role="status">' . e($f[0]) . '</div>';
}
const ICON_SEARCH = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
const ICON_HEART = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';

function public_head(string $title): void {
    head_html($title);
    $logo = setting('logo', '/assets/img/logo.png');
    echo (setting('show_deco', '1') === '1' ? deco_html() : '') . '<a class="skip" href="#main">Ir para o conteúdo</a><header class="hd' . (setting('sticky_header', '1') === '1' ? '' : ' static') . (setting('header_pattern', '1') === '1' ? ' pat' : '') . '">' . (setting('show_header_art', '1') === '1' ? header_art() : '') . '<div class="wrap hd-in">'
       . '<button class="burger" type="button" aria-label="Abrir menu" aria-expanded="false" data-menu><span></span><span></span><span></span></button>'
       . '<a class="logo" href="/" aria-label="' . e(site_name()) . '"><img src="' . e($logo) . '" alt="' . e(site_name()) . '"></a>'
       . '<form class="search" action="/produtos" method="get" role="search"><input name="q" type="search" placeholder="Buscar produtos, marcas e mais..." aria-label="Buscar produtos" maxlength="80"><button type="submit" aria-label="Buscar">' . ICON_SEARCH . '</button></form>'
       . '<a class="fav" href="/favoritos" aria-label="Favoritos">' . ICON_HEART . '<span class="fav-n" data-fav-count>0</span></a></div>'
       . '<nav class="nav" data-nav aria-label="Principal"><div class="wrap">'
       . '<a href="/">Início</a>' . (setting('show_products_menu', '1') === '1' ? '<a href="/produtos">Produtos</a>' : '') . '<a href="/categorias">Categorias</a><a href="/lojas">Lojas</a>' . (setting('show_blog_menu', '0') === '1' ? '<a href="/blog">Blog</a>' : '') . '<a href="/contato">Contato</a><a href="/cupons">Cupons</a>'
       . '</div></nav></header><main id="main">';
}
function public_foot(): void {
    $links = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'whatsapp_group' => 'Grupo no WhatsApp', 'telegram_group' => 'Grupo no Telegram'];
    echo '</main><footer class="ft"><div class="wrap ft-in"><div><strong>' . e(site_name()) . '</strong><p>' . e(setting('footer_text', 'Os melhores achadinhos de várias lojas, com links de afiliado. Preços sujeitos a alteração.')) . '</p></div><div class="ft-links">';
    if ($rows = socials_all(true)) echo socials_html($rows, 'foot'); else foreach ($links as $k => $n) if (($u = setting($k, $k === 'instagram' ? 'https://www.instagram.com/achei.legal4/' : '')) !== '') echo '<a href="' . e($u) . '" rel="noopener" target="_blank">' . $n . '</a>';
    echo '<a href="/faq">FAQ</a><a href="/termos">Termos</a><a href="/privacidade">Privacidade</a></div></div><div class="copy">© ' . date('Y') . ' ' . e(site_name()) . '. Todos os direitos reservados.</div></footer><script src="/assets/js/site.js?v=' . @filemtime(ROOT . '/public/assets/js/site.js') . '" defer></script></body></html>';
}
function admin_head(string $title, string $active = ''): void {
    head_html($title . ' · Admin', 'adm');
    $items = ['' => ['Painel', '/admin'], 'produtos' => ['Produtos', '/admin/produtos'], 'categorias' => ['Categorias', '/admin/categorias'], 'lojas' => ['Lojas', '/admin/lojas'], 'banners' => ['Banners', '/admin/banners'], 'cupons' => ['Cupons', '/admin/cupons'], 'mensagens' => ['Mensagens', '/admin/mensagens'], 'paginas' => ['Páginas', '/admin/paginas'], 'redes' => ['Redes sociais', '/admin/redes'], 'api' => ['API', '/admin/api'], 'config' => ['Configurações', '/admin/configuracoes']];
    $soon = [];
    echo '<div class="adm-shell"><aside class="adm-side"><a class="adm-brand" href="/admin"><img src="' . e(setting('logo', '/assets/img/logo.png')) . '" alt="">Admin</a><nav>';
    foreach ($items as $k => [$n, $u]) echo '<a href="' . $u . '"' . ($k === $active ? ' class="on"' : '') . '>' . $n . '</a>';
    foreach ($soon as $n) echo '<span class="soon">' . $n . ' <small>em breve</small></span>';
    echo '</nav><form method="post" action="/admin/logout">' . csrf_field() . '<button class="btn ghost" type="submit">Sair</button></form></aside><main class="adm-main"><h1>' . e($title) . '</h1>';
    flash_html();
}
function admin_foot(): void { echo '</main></div><script src="/assets/js/site.js?v=' . @filemtime(ROOT . '/public/assets/js/site.js') . '" defer></script></body></html>'; }


function art_svg(string $k): string {
    $o = '<svg viewBox="0 0 ' . (in_array($k, ['a', 'b'], true) ? '210 110' : '240 260') . '" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">';
    $spark = fn($x, $y, $s = 1) => '<path transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')" d="M0-9l2.6 6.4L9 0 2.6 2.6 0 9-2.6 2.6-9 0l6.4-2.6z" fill="#FFC83D"/>';
    $heart = fn($x, $y, $s, $c = '#fff') => '<g fill="' . $c . '" transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')"><circle cx="-6.5" cy="-3" r="6.5"/><circle cx="6.5" cy="-3" r="6.5"/><path d="M-12.5 0 0 15 12.5 0z"/></g>';
    $tag = fn($x, $y, $r, $s = 1) => '<g transform="translate(' . $x . ' ' . $y . ') rotate(' . $r . ') scale(' . $s . ')"><rect x="-27" y="-16" width="54" height="32" rx="9" fill="#F9B9C3"/><circle cx="-17" cy="-3" r="3.5" fill="#fff"/><text x="6" y="7" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="19" fill="#D55674">%</text></g>';
    $dot = fn($x, $y, $r = 4, $c = '#FFC83D') => '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="' . $c . '"/>';
    $wheel = fn($x, $y, $r) => '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="#D55674"/><circle cx="' . $x . '" cy="' . $y . '" r="' . $r * .4 . '" fill="#fff"/>';
    $o .= match ($k) {
        'a' => '<path d="M72 36h54l-5 64H77z" fill="#D55674"/><path d="M86 36v-8a13 13 0 0 1 26 0v8" fill="none" stroke="#D55674" stroke-width="5" stroke-linecap="round"/><path d="M28 52h60l-5 52H33z" fill="#F55879"/><path d="M44 52v-8a14 14 0 0 1 28 0v8" fill="none" stroke="#F55879" stroke-width="5" stroke-linecap="round"/>'
            . $heart(58, 80, .85) . $tag(158, 62, 10) . $spark(184, 22, 1.2) . $spark(112, 14, .8) . $spark(18, 36, .9) . $dot(196, 92) . $dot(12, 84, 3, '#F9B9C3'),
        'b' => '<path d="M26 22h20l11 50h72l12-38H52" fill="none" stroke="#D55674" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><path d="M56 36h88l-10 34H64z" fill="#F9B9C3"/><rect x="72" y="12" width="30" height="26" rx="3" fill="#F55879"/><path d="M87 12v26M72 25h30" stroke="#fff" stroke-width="3"/><path d="M110 22h26l-3 20h-20z" fill="#FFC83D"/><path d="M116 22v-4a7 7 0 0 1 14 0v4" fill="none" stroke="#FFC83D" stroke-width="3"/>'
            . $wheel(78, 92, 9) . $wheel(130, 92, 9) . $heart(172, 30, .7, '#F55879') . $spark(188, 70, 1) . $spark(36, 60, .8) . $dot(160, 98, 3) . $dot(24, 96, 4, '#F9B9C3'),
        'c' => '<path d="M18 40h32l22 110h118l24-76H62" fill="none" stroke="#D55674" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><path d="M66 82h148l-22 66H86z" fill="#F9B9C3"/><rect x="88" y="26" width="58" height="50" rx="7" fill="#F55879"/><path d="M117 26v50M88 51h58" stroke="#fff" stroke-width="6"/><path d="M150 46h48l-6 38h-36z" fill="#FFC83D"/><path d="M162 46v-8a13 13 0 0 1 26 0v8" fill="none" stroke="#FFC83D" stroke-width="5" stroke-linecap="round"/>'
            . $heart(60, 58, 1.1, '#F55879') . $wheel(98, 188, 17) . $wheel(176, 188, 17) . $tag(192, 224, -12, 1.2) . $spark(30, 120, 1.4) . $spark(214, 22, 1.2) . $spark(226, 120, 1) . $dot(40, 200, 5) . $dot(120, 236, 4, '#F9B9C3') . $dot(14, 160, 4, '#F9B9C3'),
        default => '<rect x="34" y="158" width="120" height="86" rx="8" fill="#F55879"/><path d="M94 158v86" stroke="#fff" stroke-width="10"/><rect x="28" y="144" width="132" height="22" rx="6" fill="#D55674"/><path d="M94 144v22" stroke="#fff" stroke-width="10"/><path d="M94 144c-30-34-52-6-28 2zM94 144c30-34 52-6 28 2z" fill="#fff"/><rect x="58" y="98" width="72" height="46" rx="6" fill="#F9B9C3"/><path d="M94 98v46" stroke="#fff" stroke-width="8"/>'
            . '<path d="M150 150h58l-7 94h-44z" fill="#FFC83D"/><path d="M164 150v-10a15 15 0 0 1 30 0v10" fill="none" stroke="#FFC83D" stroke-width="6" stroke-linecap="round"/>' . $heart(180, 192, 1, '#fff') . $tag(60, 60, -14, 1.2) . $spark(200, 60, 1.4) . $spark(20, 120, 1) . $spark(220, 118, .9) . $dot(34, 36, 5) . $dot(214, 100, 4, '#F9B9C3') . $dot(110, 40, 4, '#F9B9C3'),
    };
    return $o . '</svg>';
}
function header_art(): string { return '<div class="hd-art l" aria-hidden="true">' . art_svg('a') . '</div><div class="hd-art r" aria-hidden="true">' . art_svg('b') . '</div>'; }

function deco_html(): string { return '<div class="deco" aria-hidden="true"><div class="sa l"></div><div class="sa r"></div></div>'; }
/** Fundo clarinho com desenhos bem suaves no cabeçalho (padrão repetido em SVG, cor da marca). */
function header_pattern_css(): string {
    if (setting('header_pattern', '1') !== '1') return '';
    $h = 'M22 30s-9-5.5-9-11.5a5 5 0 0 1 9-2.4 5 5 0 0 1 9 2.4c0 6-9 11.5-9 11.5z'; $sp = 'l2.4 6.6 6.6 2.4-6.6 2.4-2.4 6.6-2.4-6.6-6.6-2.4 6.6-2.4z';
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='150' height='150' viewBox='0 0 150 150'><g fill='#F55879' fill-opacity='.11'><path d='$h'/><path d='M104 14$sp'/><circle cx='74' cy='84' r='3'/><path d='M124 104$sp'/><path transform='translate(14 88)' d='$h'/><circle cx='112' cy='62' r='2'/><circle cx='46' cy='132' r='2.5'/></g></svg>";
    $uri = strtr($svg, ['<' => '%3C', '>' => '%3E', '#' => '%23']);
    return '.hd.pat{background-color:#fff;background-image:url("data:image/svg+xml,' . $uri . '"),linear-gradient(90deg,#fff 0%,#FFF3F6 50%,#fff 100%)}@media(min-width:768px){.hd.pat .nav{background:transparent}}';
}
