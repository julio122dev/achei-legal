<?php
const SHOPEE_ENDPOINT = 'https://open-api.affiliate.shopee.com.br/graphql';
function shopee_host_ok(string $h): bool { $h = strtolower($h); foreach (['shopee.com.br', 'shope.ee', 'shp.ee'] as $d) if ($h === $d || str_ends_with($h, '.' . $d)) return true; return false; }
/** Extrai [shopId, itemId] de um link da Shopee (inclui links curtos, seguindo no máx. 3 redirecionamentos só entre domínios da Shopee). */
function shopee_ids(string $url, int $depth = 0): array {
    $url = trim($url); $p = parse_url($url);
    if (!in_array($p['scheme'] ?? '', ['http', 'https'], true) || !shopee_host_ok($p['host'] ?? '')) throw new RuntimeException('Cole um link de produto da Shopee (shopee.com.br ou link curto s.shopee.com.br).');
    if (preg_match('~/product/(\d+)/(\d+)~', $url, $m) || preg_match('~-i\.(\d+)\.(\d+)~', $url, $m) || preg_match('~/opaanlp/(\d+)/(\d+)~', $url, $m)) return [(int)$m[1], (int)$m[2]];
    if ($depth < 3) {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'follow_location' => 0, 'timeout' => 8, 'ignore_errors' => true, 'user_agent' => 'Mozilla/5.0']]);
        @file_get_contents($url, false, $ctx, 0, 1);
        foreach ($http_response_header ?? [] as $hd) if (stripos($hd, 'location:') === 0) {
            $loc = trim(substr($hd, 9)); if (str_starts_with($loc, '/')) $loc = $p['scheme'] . '://' . $p['host'] . $loc;
            return shopee_ids($loc, $depth + 1);
        }
    }
    throw new RuntimeException('Não consegui identificar o produto nesse link. Abra o produto no navegador e copie o endereço completo.');
}
function shopee_http(string $url, string $payload, string $auth): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . $auth]]);
        $r = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($r === false) throw new RuntimeException('Não consegui falar com a Shopee: ' . mb_substr($err, 0, 200));
        return (string)$r;
    }
    if (str_starts_with($url, 'https') && !in_array('https', stream_get_wrappers(), true)) throw new RuntimeException('O PHP deste computador não consegue abrir sites https: ligue extension=openssl (e extension=curl) no php.ini e reinicie o site.');
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 15, 'ignore_errors' => true, 'content' => $payload, 'header' => "Content-Type: application/json\r\nAuthorization: $auth\r\n"]]);
    $r = @file_get_contents($url, false, $ctx);
    if ($r === false) throw new RuntimeException('Não consegui falar com a Shopee: ' . mb_substr((string)(error_get_last()['message'] ?? 'erro desconhecido'), 0, 200));
    return $r;
}
function shopee_call(string $query): array {
    $id = setting('shopee_app_id'); $sec = setting('shopee_app_secret');
    if ($id === '' || $sec === '') throw new RuntimeException('Configure o App ID e o App Secret da Shopee em Configurações > Integrações.');
    $payload = json_encode(['query' => $query], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $ts = time();
    $raw = shopee_http(setting('shopee_endpoint', SHOPEE_ENDPOINT), $payload, "SHA256 Credential=$id, Timestamp=$ts, Signature=" . hash('sha256', $id . $ts . $payload . $sec));
    $j = json_decode($raw, true);
    if (!is_array($j)) throw new RuntimeException('Resposta inesperada da Shopee: ' . mb_substr(trim(strip_tags($raw)), 0, 120));
    if (!empty($j['errors'])) throw new RuntimeException('A Shopee recusou o pedido: ' . mb_substr((string)($j['errors'][0]['message'] ?? 'erro desconhecido'), 0, 160));
    return $j['data'] ?? [];
}
function page_admin_import(): void {
    require_admin(); if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'msg' => 'Método inválido.'], 405); csrf_check();
    try {
        [, $item] = shopee_ids((string)($_POST['url'] ?? ''));
        $d = shopee_call(sprintf('query { productOfferV2(itemId: %d, limit: 1) { nodes { itemId productName imageUrl price priceDiscountRate offerLink productLink } } }', $item));
        $n = $d['productOfferV2']['nodes'][0] ?? null;
        if (!$n) throw new RuntimeException('A Shopee não devolveu esse produto. Ele pode não estar no programa de afiliados.');
        $price = parse_price($n['price'] ?? ''); $rate = (float)($n['priceDiscountRate'] ?? 0);
        $old = ($price && $rate > 0 && $rate < 100) ? round($price / (1 - $rate / 100), 2) : null;
        $img = null; $warn = '';
        try { if (!empty($n['imageUrl'])) $img = api_fetch_image((string)$n['imageUrl']); } catch (Throwable $x) { $warn = 'A foto não pôde ser baixada; envie manualmente.'; }
        $link = (string)(($n['offerLink'] ?? '') ?: ($n['productLink'] ?? '')); if (!preg_match('#^https?://#i', $link)) $link = '';
        $sid = (int)db()->query("SELECT id FROM stores WHERE lower(name) LIKE '%shopee%' ORDER BY active DESC, id LIMIT 1")->fetchColumn();
        json_out(['ok' => true, 'title' => mb_substr(trim((string)$n['productName']), 0, 160), 'price' => $price, 'old_price' => $old, 'url' => $link, 'image' => $img, 'store_id' => $sid, 'warn' => $warn]);
    } catch (RuntimeException $x) { json_out(['ok' => false, 'msg' => $x->getMessage()]); }
}
