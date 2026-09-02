<?php
declare(strict_types=1);

/**
 * Hardened search.php
 * - PDO + prepared statements (mysql_* is removed)
 * - Secrets from environment, never in source
 * - Output encoding, URL allowlists, request limits
 * - No error leakage, HTTPS-only outbound HTTP
 */

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; img-src \'self\' https: data:; style-src \'self\' https://cdn.jsdelivr.net; script-src \'self\'; frame-src https://console.api.ai; form-action \'self\'; base-uri \'self\'');
header('Content-Type: text/html; charset=UTF-8');

const MIN_QUERY_LEN = 1;
const MAX_QUERY_LEN = 120;
const MAX_RESULTS = 50;
const TITLE_LOG = __DIR__ . '/title/title.txt';

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fail_safe(string $publicMessage = 'Request could not be completed.'): never
{
    http_response_code(500);
    echo '<p>' . h($publicMessage) . '</p></body></html>';
    exit;
}

function normalize_query(?string $raw): string
{
    $query = trim((string) $raw);
    $query = preg_replace('/\s+/u', ' ', $query) ?? '';
    return mb_substr($query, 0, MAX_QUERY_LEN, 'UTF-8');
}

function boolean_fulltext_terms(string $query): string
{
    // BOOLEAN MODE operators must not come from the user.
    $terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $safe = [];
    foreach ($terms as $term) {
        $term = preg_replace('/[+\-~<>()*"\\\\]/u', '', $term) ?? '';
        $term = trim($term, '.');
        if ($term === '' || mb_strlen($term, 'UTF-8') < MIN_QUERY_LEN) {
            continue;
        }
        $safe[] = '+' . $term;
    }
    return implode(' ', array_slice($safe, 0, 12));
}

function is_safe_http_url(string $url): bool
{
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower($parts['host']);
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
    return (bool) preg_match('/^[a-z0-9.-]+$/i', $host);
}

function page_url(string $hostname, string $page): string
{
    $host = strtolower(preg_replace('/[^a-z0-9.-]/i', '', $hostname) ?? '');
    $path = '/' . ltrim($page, '/');
    $path = preg_replace('#/+#', '/', $path) ?? '/';
    if ($host === '' || !is_safe_http_url('https://' . $host . $path)) {
        return '#';
    }
    return 'https://' . $host . $path;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = env('DB_DSN', 'mysql:host=127.0.0.1;dbname=ows_index;charset=utf8mb4');
    $user = env('DB_USER');
    $pass = env('DB_PASS');
    if ($user === '') {
        fail_safe();
    }
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException) {
        fail_safe();
    }
    return $pdo;
}

function http_get_json(string $url, array $headers = [], int $timeout = 8): ?array
{
    if (!is_safe_http_url($url) || strpos($url, 'https://') !== 0) {
        return null;
    }
    $headerLines = [];
    foreach ($headers as $name => $value) {
        if (!preg_match('/^[A-Za-z0-9-]+$/', $name)) {
            continue;
        }
        $headerLines[] = $name . ': ' . str_replace(["\r", "\n"], '', (string) $value);
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => implode("\r\n", $headerLines),
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function search_pages(PDO $pdo, string $booleanQuery, bool $expanded): array
{
    if ($booleanQuery === '') {
        return [];
    }
    if ($expanded) {
        $sql = 'SELECT title, anchor_text, hostname, page
                FROM pages
                WHERE title <> \'\'
                  AND (
                    MATCH (anchor_text) AGAINST (:q1 IN BOOLEAN MODE)
                    OR MATCH (title) AGAINST (:q2 IN BOOLEAN MODE)
                  )
                ORDER BY relevance_proxy DESC
                LIMIT :lim';
        // Keep ORDER deterministic without injecting user text.
        $sql = 'SELECT title, anchor_text, hostname, page,
                       (MATCH(title) AGAINST (:qRel IN BOOLEAN MODE)) AS rel
                FROM pages
                WHERE title <> \'\'
                  AND (
                    MATCH (anchor_text) AGAINST (:q1 IN BOOLEAN MODE)
                    OR MATCH (title) AGAINST (:q2 IN BOOLEAN MODE)
                  )
                ORDER BY rel DESC
                LIMIT :lim';
    } else {
        $sql = 'SELECT title, anchor_text, hostname, page,
                    ((1.3 * (MATCH(title) AGAINST (:qRel IN BOOLEAN MODE)))
                    + (0.6 * (MATCH(anchor_text) AGAINST (:qRel2 IN BOOLEAN MODE)))) AS relevance
                FROM pages
                WHERE MATCH(title, anchor_text) AGAINST (:q1 IN BOOLEAN MODE)
                ORDER BY relevance DESC
                LIMIT :lim';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':q1', $booleanQuery, PDO::PARAM_STR);
    $stmt->bindValue(':lim', MAX_RESULTS, PDO::PARAM_INT);
    if ($expanded) {
        $stmt->bindValue(':q2', $booleanQuery, PDO::PARAM_STR);
        $stmt->bindValue(':qRel', $booleanQuery, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':qRel', $booleanQuery, PDO::PARAM_STR);
        $stmt->bindValue(':qRel2', $booleanQuery, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function bing_search(string $query): array
{
    $key = env('BING_SUBSCRIPTION_KEY');
    if ($key === '') {
        return [];
    }
    $endpoint = 'https://api.bing.microsoft.com/v7.0/search?' . http_build_query([
        'q' => $query,
        'count' => 10,
        'offset' => 0,
        'mkt' => 'en-US',
        'safeSearch' => 'Moderate',
    ], '', '&', PHP_QUERY_RFC3986);
    $json = http_get_json($endpoint, [
        'Ocp-Apim-Subscription-Key' => $key,
        'Accept' => 'application/json',
    ]);
    $items = $json['webPages']['value'] ?? [];
    return is_array($items) ? $items : [];
}

function get_weather(string $city): void
{
    $city = preg_replace('/[^a-zA-Z0-9 ,.\'-]/u', '', $city) ?? '';
    $city = trim($city);
    if ($city === '' || mb_strlen($city, 'UTF-8') > 60) {
        echo '<p>Weather location is invalid.</p>';
        return;
    }
    $appid = env('OPENWEATHER_APPID');
    if ($appid === '') {
        echo '<p>Weather is unavailable.</p>';
        return;
    }
    $url = 'https://api.openweathermap.org/data/2.5/weather?' . http_build_query([
        'q' => $city,
        'units' => 'metric',
        'APPID' => $appid,
    ], '', '&', PHP_QUERY_RFC3986);
    $weather = http_get_json($url);
    if ($weather === null || empty($weather['weather'][0]['icon'])) {
        echo '<p>Weather data could not be loaded.</p>';
        return;
    }
    $icon = preg_replace('/[^a-z0-9]/i', '', (string) $weather['weather'][0]['icon']) ?? '';
    echo '<img src="https://openweathermap.org/img/w/' . h($icon) . '.png" alt="" width="200" height="200">';
    echo '<br>';
    echo h((string) ($weather['weather'][0]['main'] ?? '')) . ' ';
    echo 'Temperature ' . h((string) ($weather['main']['temp'] ?? '')) . '°C ';
    echo 'Humidity: ' . h((string) ($weather['main']['humidity'] ?? '')) . '% ';
    echo h((string) ($weather['wind']['speed'] ?? '')) . ' m/s<br>';
}

function think(string $queryInput): void
{
    $year = (int) date('Y');
    $tsubasaAge = $year - 1989;
    if ((int) date('n') < 9) {
        $tsubasaAge--;
    }
    $age = (string) $tsubasaAge;

    if (preg_match('/hello/i', $queryInput) || $queryInput === 'hi' || preg_match('/hi!/i', $queryInput)) {
        echo 'Hi, I\'m glad you decided to talk to me.<br>';
        echo 'I was created by Tsubasa Kato, age ' . h($age) . ' a.k.a stingraze.<br>';
        echo 'You can talk to me more below.<br>';
        echo '<iframe width="350" height="430" title="Assistant" src="https://console.api.ai/api-client/demo/embedded/c2b9ee77-8217-4d92-8712-b4c21f50261d"></iframe><br>';
    }
    if (preg_match('/bonjour/i', $queryInput)) {
        echo 'Bonjour!<br>';
    }
    if (preg_match('/good morning/i', $queryInput)) {
        echo 'Good morning! How\'s the weather at your place?<br>';
    }
    if (preg_match('/sing for me/i', $queryInput)) {
        echo 'I can\'t sing yet, but wait for improvements made in the near future!<br>';
        echo 'Check out <a href="' . h('../../demo/tts.html') . '">this demo</a> if you\'d like.<br>';
    }
    if (preg_match('/weather for/i', $queryInput) && str_word_count($queryInput) >= 3) {
        if (preg_match('/[^ ]*$/', $queryInput, $lastWord)) {
            echo 'Today\'s weather for ' . h($lastWord[0]) . '<br>';
            get_weather($lastWord[0]);
        }
    }
}

function render_hits(array $rows): void
{
    foreach ($rows as $row) {
        $title = h((string) ($row['title'] ?? ''));
        $anchor = h((string) ($row['anchor_text'] ?? ''));
        $href = h(page_url((string) ($row['hostname'] ?? ''), (string) ($row['page'] ?? '')));
        $display = h((string) ($row['hostname'] ?? '') . (string) ($row['page'] ?? ''));
        echo '<p><h3><a href="' . $href . '" rel="noopener noreferrer nofollow">' . $title . '</a></h3>'
            . $display . '<br><br>' . $anchor . '</p>' . PHP_EOL;
    }
}

function append_title_log(string $title): void
{
    $dir = dirname(TITLE_LOG);
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    $line = mb_substr($title, 0, 300, 'UTF-8') . PHP_EOL;
    file_put_contents(TITLE_LOG, $line, FILE_APPEND | LOCK_EX);
}

$query = normalize_query($_GET['query'] ?? '');
$booleanQuery = boolean_fulltext_terms($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $query !== '' ? 'SuperAI.online - Search results for: ' . h($query) : 'SuperAI.online Search'; ?></title>
<link rel="stylesheet" href="./search-style/style2.css">
</head>
<body>
<section id="banner2">
<br>
<h1><a href="search.html"><img src="superai-logo.png" alt="SuperAI Search"></a></h1>
<p>The next generation AI Search Engine Powered by Mohawk Search.</p>
</section>
<div class="container">
<form action="search.php" method="get" accept-charset="UTF-8">
<div class="row">
<div id="custom-search-input">
<div class="input-group col-md-12">
<input type="text" class="form-control input-lg" name="query" id="query" maxlength="<?php echo (int) MAX_QUERY_LEN; ?>" value="<?php echo h($query); ?>" required>
<span class="input-group-btn">
<button class="btn btn-info btn-lg" type="submit">Search</button>
</span>
</div>
</div>
</div>
</form>
<?php
if (mb_strlen($query, 'UTF-8') < MIN_QUERY_LEN) {
    echo 'Minimum length is ' . (int) MIN_QUERY_LEN;
} else {
    think($query);
    echo 'You searched for: ' . h($query) . '<br><br>';

    try {
        $pdo = db();
        $rawResults = search_pages($pdo, $booleanQuery, false);
        if ($rawResults) {
            render_hits($rawResults);
        } else {
            echo 'No results from SuperAI.online with strict matching, expanded results...';
            render_hits(search_pages($pdo, $booleanQuery, true));
            echo '<br>Results from Bing<br><p>';
            $lastTitle = '';
            foreach (bing_search($query) as $item) {
                $url = (string) ($item['url'] ?? '');
                if (!is_safe_http_url($url)) {
                    continue;
                }
                echo '<a href="' . h($url) . '" rel="noopener noreferrer nofollow">' . h((string) ($item['name'] ?? '')) . '</a><br>';
                echo h((string) ($item['snippet'] ?? '')) . '<br>';
                echo h((string) ($item['displayUrl'] ?? '')) . '<br><br>';
                $lastTitle = (string) ($item['name'] ?? '');
            }
            echo '</p>';
            if ($lastTitle !== '') {
                append_title_log($lastTitle);
            }
        }
    } catch (Throwable) {
        fail_safe();
    }
}
?>
</div>
</body>
</html>
