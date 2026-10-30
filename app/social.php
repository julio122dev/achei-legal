<?php
const SOCIAL_PRESETS = [
    'instagram' => ['Instagram', 'https://www.instagram.com/seu_usuario/'], 'facebook' => ['Facebook', 'https://www.facebook.com/sua_pagina'],
    'tiktok' => ['TikTok', 'https://www.tiktok.com/@seu_usuario'], 'youtube' => ['YouTube', 'https://www.youtube.com/@seu_canal'],
    'whatsapp' => ['WhatsApp', 'https://wa.me/5511999999999 ou link do grupo'], 'telegram' => ['Telegram', 'https://t.me/seu_canal'],
    'x' => ['X (Twitter)', 'https://x.com/seu_usuario'], 'pinterest' => ['Pinterest', 'https://br.pinterest.com/seu_usuario/'],
    'email' => ['E-mail', 'mailto:contato@seusite.com.br'], 'link' => ['Outro link', 'https://...'],
];
function social_bg(string $k): string {
    return ['instagram' => 'linear-gradient(45deg,#F58529,#DD2A7B,#8134AF)', 'facebook' => '#1877F2', 'tiktok' => '#111', 'youtube' => '#F00', 'whatsapp' => '#25D366',
        'telegram' => '#229ED9', 'x' => '#111', 'pinterest' => '#E60023'][$k] ?? 'var(--brand)';
}
function social_icon(string $k): string {
    $o = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" ';
    $st = 'fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
    return match ($k) {
        'instagram' => $o . $st . '><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.3" cy="6.7" r=".7" fill="#fff"/></svg>',
        'facebook' => $o . 'fill="#fff"><path d="M13.5 21v-8h2.7l.5-3.3h-3.2V7.6c0-1 .4-1.7 1.8-1.7h1.5V3.1C16.1 3 15.2 3 14.2 3c-2.6 0-4.4 1.6-4.4 4.4v2.3H7v3.3h2.8v8h3.7z"/></svg>',
        'tiktok' => $o . 'fill="none" stroke="#fff" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v11.2a3.7 3.7 0 1 1-3.7-3.7"/><path d="M14 3c.3 2.6 2 4.4 4.7 4.7"/></svg>',
        'youtube' => $o . '><rect x="2.5" y="6" width="19" height="12" rx="3.6" fill="#fff"/><path d="M10 9.2v5.6l5-2.8z" fill="#F00"/></svg>',
        'whatsapp' => $o . $st . '><path d="M3.5 20.5l1.3-4.4A8.6 8.6 0 1 1 8 19.4z"/><path d="M9 8.8c.2 3 2.6 5.6 5.8 6.2l1.1-1.4-2-1-.8.7c-.8-.3-1.7-1.2-2.1-2.1l.7-.8-1-2z" fill="#fff" stroke="none"/></svg>',
        'telegram' => $o . 'fill="#fff"><path d="M21 4 3 11l5.5 2L10 19l3-3.5 4.5 3.3z"/></svg>',
        'x' => $o . 'fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M5 5l14 14M19 5 5 19"/></svg>',
        'pinterest' => $o . '><text x="12" y="18" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="17" fill="#fff">P</text></svg>',
        'email' => $o . $st . '><rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="m4 7.5 8 6 8-6"/></svg>',
        default => $o . 'fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>',
    };
}
function socials_all(bool $onlyActive = false): array {
    try {
        if (setting('socials_seeded') === '' && installed()) {
            if (!(int)db()->query('SELECT COUNT(*) FROM socials')->fetchColumn())
                db()->prepare('INSERT INTO socials(kind,name,url,sort) VALUES(?,?,?,1)')->execute(['instagram', 'Instagram', setting('instagram', 'https://www.instagram.com/achei.legal4/')]);
            set_setting('socials_seeded', '1');
        }
        return db()->query('SELECT * FROM socials ' . ($onlyActive ? 'WHERE active=1 ' : '') . 'ORDER BY sort,id')->fetchAll();
    } catch (Throwable $x) { return []; }
}
function socials_html(array $rows, string $cls): string {
    $h = '<ul class="soc ' . e($cls) . '">';
    foreach ($rows as $r) $h .= '<li><a class="soc-a" href="' . e($r['url']) . '" target="_blank" rel="noopener noreferrer" aria-label="' . e($r['name']) . '"><span class="soc-i" style="background:' . social_bg($r['kind']) . '">' . social_icon($r['kind']) . '</span><span class="soc-n">' . e($r['name']) . '</span></a></li>';
    return $h . '</ul>';
}
function socials_section(): void {
    if (setting('show_socials', '1') !== '1' || !($rows = socials_all(true))) return;
    echo '<section class="wrap sec soc-sec slim"><h2>Siga a gente</h2>' . socials_html($rows, 'sm') . '</section>';
}
function page_admin_socials(): void {
    require_admin(); socials_all();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check(); $a = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
        if ($a === 'add') {
            $k = isset(SOCIAL_PRESETS[$_POST['kind'] ?? '']) ? $_POST['kind'] : 'link'; $url = trim((string)($_POST['url'] ?? ''));
            $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 40) ?: ($k === 'link' ? '' : SOCIAL_PRESETS[$k][0]);
            if ($name === '' || !(preg_match('#^https?://#i', $url) || preg_match('#^mailto:[^\s@]+@[^\s@]+$#i', $url)) || mb_strlen($url) > 300) flash('Informe o nome (para "Outro link") e um endereço que comece com https:// ou mailto:', 'err');
            else { db()->prepare('INSERT INTO socials(kind,name,url,sort) VALUES(?,?,?,?)')->execute([$k, $name, $url, (int)($_POST['sort'] ?? 0)]); flash('Rede social adicionada.'); }
        } elseif ($a === 'toggle') { db()->prepare('UPDATE socials SET active=1-active WHERE id=?')->execute([$id]); flash('Status atualizado.'); }
        elseif ($a === 'delete') { db()->prepare('DELETE FROM socials WHERE id=?')->execute([$id]); flash('Rede removida.'); }
        redirect('/admin/redes');
    }
    admin_head('Redes sociais', 'redes'); $opts = ''; foreach (SOCIAL_PRESETS as $k => [$n]) $opts .= '<option value="' . $k . '">' . e($n) . '</option>';
    echo '<form class="form card-form" method="post">' . csrf_field() . '<input type="hidden" name="action" value="add"><h2>Adicionar rede social</h2><div class="grid2"><label>Rede<select name="kind">' . $opts . '</select></label>'
       . '<label>Nome exibido (opcional)<input name="name" maxlength="40" placeholder="Ex.: Meu grupo de ofertas"></label><label>Endereço (link)<input name="url" required maxlength="300" placeholder="https://www.instagram.com/seu_usuario/"></label><label>Ordem<input type="number" name="sort" value="10"></label></div>'
       . '<p class="muted">Exemplos: Instagram <code>https://www.instagram.com/seu_usuario/</code> · WhatsApp <code>https://wa.me/5511999999999</code> ou o link do grupo · E-mail <code>mailto:contato@seusite.com.br</code>. Para outra rede (Kwai, Threads...), escolha "Outro link".</p><button class="btn" type="submit">Adicionar</button></form>'
       . '<div class="table-wrap"><table class="tbl"><thead><tr><th>Rede</th><th>Endereço</th><th>Ativa</th><th></th></tr></thead><tbody>';
    $rows = socials_all(); if (!$rows) echo '<tr><td colspan="4" class="empty">Nenhuma rede ainda.</td></tr>';
    foreach ($rows as $r) echo '<tr><td><span class="soc-i sm" style="background:' . social_bg($r['kind']) . '">' . social_icon($r['kind']) . '</span> <strong>' . e($r['name']) . '</strong><br><small class="muted">ordem ' . (int)$r['sort'] . '</small></td><td><small>' . e($r['url']) . '</small></td>'
        . '<td><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="pill ' . ($r['active'] ? 'on' : '') . '" type="submit">' . ($r['active'] ? 'Ativa' : 'Oculta') . '</button></form></td>'
        . '<td><form method="post" data-confirm="Remover ' . e($r['name']) . '?">' . csrf_field() . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$r['id'] . '"><button class="btn danger sm" type="submit">Excluir</button></form></td></tr>';
    echo '</tbody></table></div>'; admin_foot();
}
