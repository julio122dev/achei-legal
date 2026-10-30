<?php
/** Conta a visita (1 por pessoa por dia + páginas vistas). Ignora admin, robôs, cliques e arquivos. */
function track_visit(string $path): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || is_admin()) return;
    if (preg_match('#^/(admin|api|ir|cupom|assets|uploads|install)(/|$)#', $path) || preg_match('#\.(xml|txt|ico|png|jpg|webp|css|js|svg)$#i', $path)) return;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? ''); if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless/i', $ua)) return;
    try {
        $day = date('Y-m-d');
        db()->prepare('INSERT INTO site_visits(day,ip_hash) VALUES(?,?) ON CONFLICT(day,ip_hash) DO UPDATE SET hits=hits+1')->execute([$day, hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua . '|' . $day)]);
    } catch (Throwable $x) { /* contador nunca pode derrubar o site */ }
}
/** Números do painel. */
function visit_stats(): array {
    $q = function (string $sql, array $a = []): int { $s = db()->prepare($sql); $s->execute($a); return (int)$s->fetchColumn(); };
    $d = fn(int $n) => date('Y-m-d', strtotime("-$n days"));
    return [
        'today' => $q('SELECT COUNT(*) FROM site_visits WHERE day=?', [date('Y-m-d')]),
        'pv_today' => $q('SELECT COALESCE(SUM(hits),0) FROM site_visits WHERE day=?', [date('Y-m-d')]),
        'd7' => $q('SELECT COUNT(*) FROM site_visits WHERE day>=?', [$d(6)]),
        'd30' => $q('SELECT COUNT(*) FROM site_visits WHERE day>=?', [$d(29)]),
        'total' => $q('SELECT COUNT(*) FROM site_visits'),
    ];
}
