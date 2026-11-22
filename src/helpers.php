<?php
// Small global helpers used by pages and templates.

/** Read a config value with dotted keys, e.g. config('db.host'). Environment variables override the file. */
function config(?string $key = null, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'db'  => ['host' => 'localhost', 'port' => 5432, 'name' => 'passgo', 'user' => 'passgo', 'password' => '', 'schema' => null],
            'app' => ['debug' => false, 'timezone' => 'Europe/Lisbon'],
        ];
        $file = PASSGO_ROOT . '/config/config.php';
        if (is_file($file)) {
            $cfg = array_replace_recursive($cfg, require $file);
        }
        $env = [
            'host' => 'PASSGO_DB_HOST', 'port' => 'PASSGO_DB_PORT', 'name' => 'PASSGO_DB_NAME',
            'user' => 'PASSGO_DB_USER', 'password' => 'PASSGO_DB_PASSWORD', 'schema' => 'PASSGO_DB_SCHEMA',
        ];
        // A single DATABASE_URL (as given by Neon, Render, Heroku...) is accepted too.
        $url = getenv('DATABASE_URL');
        if ($url !== false && $url !== '') {
            $p = parse_url($url);
            if ($p !== false && isset($p['host'])) {
                $cfg['db']['host'] = $p['host'];
                $cfg['db']['port'] = isset($p['port']) ? (int) $p['port'] : 5432;
                $cfg['db']['name'] = isset($p['path']) ? ltrim(rawurldecode($p['path']), '/') : $cfg['db']['name'];
                $cfg['db']['user'] = isset($p['user']) ? rawurldecode($p['user']) : $cfg['db']['user'];
                $cfg['db']['password'] = isset($p['pass']) ? rawurldecode($p['pass']) : '';
                if (isset($p['query'])) {
                    parse_str($p['query'], $q);
                    if (isset($q['sslmode'])) {
                        $cfg['db']['sslmode'] = (string) $q['sslmode'];
                    }
                }
            }
        }
        $env['sslmode'] = 'PASSGO_DB_SSLMODE';
        foreach ($env as $k => $name) {
            $v = getenv($name);
            if ($v !== false && $v !== '') {
                $cfg['db'][$k] = $v;
            }
        }
    }
    if ($key === null) {
        return $cfg;
    }
    $value = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape a value for safe output inside HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Folder of the running script, so the app works from any sub-folder (e.g. /~user/passgo/public). */
function base_path(): string
{
    $dir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    return rtrim($dir, '/');
}

/** Build a URL relative to the public folder. */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Redirect and stop. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Page we are on, relative to the public folder (used for "return to after login"). */
function current_relative_uri(): string
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'index.php';
    $path = (string) parse_url($uri, PHP_URL_PATH);
    $query = (string) parse_url($uri, PHP_URL_QUERY);
    $name = basename($path);
    if ($name === '' || substr($name, -4) !== '.php') {
        $name = 'index.php';
    }
    return $name . ($query !== '' ? '?' . $query : '');
}

/** Only allow redirects to our own pages (prevents open redirects). */
function safe_next(?string $next): string
{
    if ($next !== null && preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $next)) {
        return $next;
    }
    return 'index.php';
}

/** Parse a timestamp coming from the database into a DateTime in the app time zone. */
function to_datetime($value): DateTime
{
    $dt = new DateTime((string) $value);
    $dt->setTimezone(new DateTimeZone((string) config('app.timezone', 'Europe/Lisbon')));
    return $dt;
}

function fmt_date($value): string      { return to_datetime($value)->format('D j M'); }
function fmt_time($value): string      { return to_datetime($value)->format('H:i'); }
function fmt_datetime($value): string  { return to_datetime($value)->format('D j M, H:i'); }
function fmt_day_key($value): string   { return to_datetime($value)->format('Y-m-d'); }

/** Render a full error page and stop. */
function abort(int $code, string $message): void
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    $titles = [400 => 'Request not accepted', 403 => 'Not allowed', 404 => 'Page not found', 500 => 'Something went wrong'];
    page_header(isset($titles[$code]) ? $titles[$code] : 'Error');
    echo '<section class="empty-state"><h1>' . e(isset($titles[$code]) ? $titles[$code] : 'Error') . '</h1>';
    echo '<p>' . e($message) . '</p>';
    echo '<a class="btn btn-primary" href="' . e(url('index.php')) . '">Back to home</a></section>';
    page_footer();
    exit;
}

/** Read an integer from $_GET or $_POST; null when missing or invalid. */
function input_int(string $key, string $source = 'GET'): ?int
{
    $bag = $source === 'POST' ? $_POST : $_GET;
    if (!isset($bag[$key]) || !is_string($bag[$key]) || !preg_match('/^\d{1,9}$/', $bag[$key])) {
        return null;
    }
    return (int) $bag[$key];
}

/** Read a trimmed string from $_GET or $_POST. */
function input_str(string $key, string $source = 'GET'): string
{
    $bag = $source === 'POST' ? $_POST : $_GET;
    return isset($bag[$key]) && is_string($bag[$key]) ? trim($bag[$key]) : '';
}

function is_post(): bool
{
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}
