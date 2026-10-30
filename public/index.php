<?php
require __DIR__ . '/../app/bootstrap.php';
require ROOT . '/app/views.php';
require ROOT . '/app/controllers.php';
require ROOT . '/app/shop.php';
require ROOT . '/app/admin_shop.php';
require ROOT . '/app/admin_site.php';
require ROOT . '/app/pages.php';
require ROOT . '/app/seed.php';
require ROOT . '/app/api.php';
require ROOT . '/app/shopee.php';
require ROOT . '/app/social.php';
require ROOT . '/app/coupons.php';
require ROOT . '/app/stats.php';

$path = mb_strtolower(rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/');
if (PHP_SAPI === 'cli-server' && $path !== '/' && is_file(__DIR__ . $path)) return false;

if (!installed() && $path !== '/install') redirect('/install');
track_visit($path);

match (true) {
    $path === '/install'      => page_install(),
    $path === '/'             => page_home(),
    $path === '/admin'        => page_admin_dashboard(),
    $path === '/admin/login'  => page_login(),
    $path === '/admin/logout' && $_SERVER['REQUEST_METHOD'] === 'POST' => page_logout(),
    $path === '/admin/lojas'  => page_admin_stores(),
    $path === '/produtos' || $path === '/favoritos' && ($_GET['fav'] = 1) => page_products(),
    preg_match('#^/produto/([a-z0-9-]+)$#', $path, $mm) === 1 => page_product($mm[1]),
    preg_match('#^/ir/(\d+)$#', $path, $mm) === 1 => page_go((int)$mm[1]),
    $path === '/admin/categorias' => page_admin_categories(),
    $path === '/admin/produtos'   => page_admin_products(),
    $path === '/admin/produtos/novo' => page_admin_product_form(null),
    preg_match('#^/admin/produtos/(\d+)$#', $path, $mm) === 1 => page_admin_product_form((int)$mm[1]),
    $path === '/admin/banners'       => page_admin_banners(),
    $path === '/admin/configuracoes' => page_admin_settings(),
    $path === '/contato'      => page_contact(),
    $path === '/categorias'   => page_categories(),
    $path === '/lojas'        => page_stores(),
    in_array($path, ['/faq', '/termos', '/privacidade'], true) => page_info(substr($path, 1)),
    $path === '/sitemap.xml'  => page_sitemap(),
    $path === '/robots.txt'   => page_robots(),
    preg_match('#^/categoria/([a-z0-9-]+)$#', $path, $mm) === 1 => page_category($mm[1]),
    preg_match('#^/loja/([a-z0-9-]+)$#', $path, $mm) === 1 => page_store($mm[1]),
    $path === '/admin/mensagens' => page_admin_messages(),
    $path === '/admin/paginas'   => page_admin_pages(),
    $path === '/admin/dev'       => page_admin_dev(),
    str_starts_with($path, '/api/')   => api_router($path),
    $path === '/compartilhar-email'   => page_share_email(),
    $path === '/admin/api'            => page_admin_api(),
    $path === '/admin/importar'      => page_admin_import(),
    $path === '/admin/redes'         => page_admin_socials(),
    $path === '/admin/categorias/rapida' => page_admin_cat_quick(),
    preg_match('#^/cupom/(\d+)$#', $path, $mm) === 1 => page_coupon_go((int)$mm[1]),
    preg_match('#^/banner/(\d+)$#', $path, $mm) === 1 => page_banner_go((int)$mm[1]),
    preg_match('#^/admin/banners/(\d+)$#', $path, $mm) === 1 => page_admin_banner_edit((int)$mm[1]),
    $path === '/admin/cupons'        => page_admin_coupons(),
    preg_match('#^/admin/cupons/(\d+)$#', $path, $mm) === 1 => page_admin_coupon_edit((int)$mm[1]),
    $path === '/cupons'              => page_coupons(),
    preg_match('#^/admin/lojas/(\d+)$#', $path, $mm) === 1 => page_admin_store_edit((int)$mm[1]),
    default                   => page_soon(),
};
