<?php
function create_schema(PDO $db): void {
$db->exec("
CREATE TABLE IF NOT EXISTS admin_users(id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT);
CREATE TABLE IF NOT EXISTS login_attempts(id INTEGER PRIMARY KEY, ip_hash TEXT, ts INTEGER);
CREATE TABLE IF NOT EXISTS stores(id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, logo TEXT, color TEXT DEFAULT '#F55879', sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1);
CREATE TABLE IF NOT EXISTS categories(id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, parent_id INTEGER, image TEXT, sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, description TEXT, short_desc TEXT, sku TEXT, base_price REAL, featured INTEGER DEFAULT 0, keywords TEXT, video_type TEXT DEFAULT 'none', video_url TEXT, views INTEGER DEFAULT 0, status TEXT DEFAULT 'published', created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT);
CREATE TABLE IF NOT EXISTS product_images(id INTEGER PRIMARY KEY, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE, path TEXT NOT NULL, sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS product_category(product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE, category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE, PRIMARY KEY(product_id,category_id));
CREATE TABLE IF NOT EXISTS affiliate_links(id INTEGER PRIMARY KEY, product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE, store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE, url TEXT NOT NULL, currency TEXT DEFAULT 'BRL', price REAL, old_price REAL, button_text TEXT, new_tab INTEGER DEFAULT 1, sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS banners(id INTEGER PRIMARY KEY, mode TEXT DEFAULT 'image', title TEXT, subtitle TEXT, btn_text TEXT, btn_url TEXT, img_desktop TEXT, img_mobile TEXT, grad_type TEXT DEFAULT 'linear', grad_dir TEXT DEFAULT '90deg', color1 TEXT, color2 TEXT, op1 REAL DEFAULT 1, op2 REAL DEFAULT 1, link TEXT, active INTEGER DEFAULT 1, sort INTEGER DEFAULT 0);
CREATE TABLE IF NOT EXISTS blog_categories(id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT UNIQUE NOT NULL);
CREATE TABLE IF NOT EXISTS posts(id INTEGER PRIMARY KEY, title TEXT NOT NULL, slug TEXT UNIQUE NOT NULL, image TEXT, content TEXT, category_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS pages(slug TEXT PRIMARY KEY, title TEXT, content TEXT);
CREATE TABLE IF NOT EXISTS views_log(id INTEGER PRIMARY KEY, product_id INTEGER, ip_hash TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS click_log(id INTEGER PRIMARY KEY, link_id INTEGER, product_id INTEGER, store_id INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS api_keys(id INTEGER PRIMARY KEY, name TEXT, key_hash TEXT NOT NULL, revoked INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS messages(id INTEGER PRIMARY KEY, name TEXT, email TEXT, whatsapp TEXT, body TEXT, is_read INTEGER DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE INDEX IF NOT EXISTS idx_links_product ON affiliate_links(product_id);
CREATE INDEX IF NOT EXISTS idx_links_store ON affiliate_links(store_id);
CREATE INDEX IF NOT EXISTS idx_prod_status ON products(status, created_at);
");
}
function ensure_socials(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS coupons(id INTEGER PRIMARY KEY, title TEXT NOT NULL, subtitle TEXT DEFAULT "", code TEXT DEFAULT "", btn_text TEXT DEFAULT "", url TEXT NOT NULL, store_id INTEGER, place TEXT DEFAULT "mid", start_at TEXT, end_at TEXT, active INTEGER DEFAULT 1, sort INTEGER DEFAULT 0, clicks INTEGER DEFAULT 0)');
    $have = $db->query('PRAGMA table_info(coupons)')->fetchAll(PDO::FETCH_COLUMN, 1);
    foreach (['store_id' => 'INTEGER', 'color' => 'TEXT DEFAULT ""', 'logo' => 'TEXT DEFAULT ""'] as $col => $def) if (!in_array($col, $have, true)) $db->exec("ALTER TABLE coupons ADD COLUMN $col $def");
    $db->exec('CREATE TABLE IF NOT EXISTS site_visits(day TEXT NOT NULL, ip_hash TEXT NOT NULL, hits INTEGER DEFAULT 1, PRIMARY KEY(day, ip_hash))');
    if (!in_array('clicks', $db->query('PRAGMA table_info(banners)')->fetchAll(PDO::FETCH_COLUMN, 1), true)) $db->exec('ALTER TABLE banners ADD COLUMN clicks INTEGER DEFAULT 0');
    $db->exec('CREATE TABLE IF NOT EXISTS socials(id INTEGER PRIMARY KEY, kind TEXT NOT NULL, name TEXT NOT NULL, url TEXT NOT NULL, sort INTEGER DEFAULT 0, active INTEGER DEFAULT 1)');
}
