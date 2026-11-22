<?php
// A minimal "browser" for flow tests: keeps cookies, follows redirects, parses forms.

class Browser
{
    private $base;
    private $jar;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
        $this->jar = tempnam(sys_get_temp_dir(), 'passgo_jar_');
    }

    public function __destruct()
    {
        if (is_file($this->jar)) {
            unlink($this->jar);
        }
    }

    /** @return array ['status' => int, 'body' => string, 'url' => string, 'location' => string] */
    public function request(string $method, string $path, array $fields = [], bool $follow = true): array
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HEADER         => false,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $locations = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($c, $h) use (&$locations) {
            if (stripos($h, 'Location:') === 0) {
                $locations[] = trim(substr($h, 9));
            }
            return strlen($h);
        });
        $body = curl_exec($ch);
        $result = [
            'status'   => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'body'     => is_string($body) ? $body : '',
            'url'      => (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            'location' => $locations ? $locations[0] : '',
        ];
        curl_close($ch);
        return $result;
    }

    public function get(string $path, bool $follow = true): array
    {
        return $this->request('GET', $path, [], $follow);
    }

    public function post(string $path, array $fields = [], bool $follow = true): array
    {
        return $this->request('POST', $path, $fields, $follow);
    }

    /** Read the CSRF token that a page placed in its forms. */
    public function csrfFrom(string $path): string
    {
        $page = $this->get($path);
        if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['body'], $m)) {
            fail("No CSRF token found on $path (status {$page['status']})");
        }
        return $m[1];
    }

    /** POST to a path after fetching a CSRF token from $formPage. */
    public function submit(string $formPage, string $action, array $fields = [], bool $follow = true): array
    {
        $fields['csrf'] = $this->csrfFrom($formPage);
        return $this->post($action, $fields, $follow);
    }

    public function register(string $name, string $email, string $password): array
    {
        return $this->submit('register.php', 'register.php', [
            'name' => $name, 'email' => $email, 'password' => $password, 'confirm' => $password,
        ]);
    }

    public function login(string $email, string $password, string $next = 'index.php'): array
    {
        return $this->submit('login.php', 'login.php', ['email' => $email, 'password' => $password, 'next' => $next]);
    }
}

/** Start PHP's built-in web server for the public/ folder. Returns [process, baseUrl]. */
function start_test_server(): array
{
    $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    $port = (int) substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
    fclose($sock);

    $env = getenv();
    $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', PASSGO_ROOT . '/public'];
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/passgo_test_server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/passgo_test_server.log', 'a']], $pipes, PASSGO_ROOT, $env);
    $base = 'http://127.0.0.1:' . $port;
    for ($i = 0; $i < 50; $i++) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($fp) {
            fclose($fp);
            return [$proc, $base];
        }
        usleep(100000);
    }
    fail('The test web server did not start');
    return [null, $base];
}

function stop_test_server($proc): void
{
    if (is_resource($proc)) {
        proc_terminate($proc);
        proc_close($proc);
    }
}
