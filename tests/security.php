<?php
/** Security regression checks. Run: php tests/security.php */
declare(strict_types=1);

$source = dirname(__DIR__) . '/htdocs';
$fixture = sys_get_temp_dir() . '/revisionhub-security-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
$fixture = realpath($fixture); // Match APP_ROOT's canonical separators on Windows.
function copy_tree(string $from, string $to): void
{
    foreach (new DirectoryIterator($from) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        $dest = $to . '/' . $entry->getFilename();
        if ($entry->isDir()) {
            mkdir($dest, 0700);
            copy_tree($entry->getPathname(), $dest);
        } else {
            copy($entry->getPathname(), $dest);
        }
    }
}
function remove_tree(string $dir): void
{
    foreach (new DirectoryIterator($dir) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir() && !$entry->isLink()) {
            remove_tree($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($dir);
}
function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

$server = null;
try {
    copy_tree($source, $fixture);
    // Never load deployment credentials in tests.
    $settings = [
        'db' => ['name' => 'YOUR_DB_NAME'],
        'app' => ['install_token' => str_repeat('a', 64)],
    ];
    file_put_contents($fixture . '/config/config.php', '<?php return ' . var_export($settings, true) . ';');
    // Don't copy a deployment-specific override into the test configuration.
    define('APP_BOOTSTRAPPED', true);
    define('APP_ROOT', $fixture);
    $testBase = '';
    function config(string $key, $default = null) {
        global $testBase;
        return $key === 'app.base_path' ? $testBase : $default;
    }
    require $source . '/includes/uploads.php';
    require $source . '/includes/functions.php';
    require $source . '/includes/sanitize.php';
    check(validate_media_url('https://example.com/paper.pdf') !== null, 'valid media URL accepted');
    check(public_media_url('https://example.com/paper.pdf') === 'https://example.com/paper.pdf', 'stored external media is revalidated before rendering');
    foreach (['javascript:alert(1)', '//example.com/paper.pdf', '/config/config.php', '/uploads/missing.pdf'] as $unsafe) {
        check(public_media_url($unsafe) === null, 'invalid stored media is not rendered: ' . $unsafe);
    }
    foreach (['javascript:alert(1)', '//example.com/a', 'https://user:pass@example.com/a', 'https://example.com\\@evil.test/a', 'https://bad host/a'] as $url) {
        check(validate_media_url($url) === null, 'unsafe media URL rejected: ' . $url);
    }
    check(sanitize_link_url('/\\evil.test') === null, 'backslash link rejected');
    check(sanitize_image_src('/uploads/\\evil.test') === null, 'backslash image rejected');
    $cleanHtml = sanitize_note_html('<p onclick="alert(1)">Safe<script>alert(1)</script><a href="javascript:alert(1)">link</a><img src="https://example.com/a.png" onerror="alert(1)"></p>');
    check(strpos($cleanHtml, 'onclick') === false && strpos($cleanHtml, '<script') === false
        && strpos($cleanHtml, 'javascript:') === false && strpos($cleanHtml, 'onerror') === false
        && strpos($cleanHtml, 'https://example.com/a.png') !== false, 'rich text strips active content and retains safe images');

    mkdir($fixture . '/uploads-sibling');
    file_put_contents($fixture . '/uploads-sibling/keep.txt', 'keep');
    file_put_contents($fixture . '/uploads/legacy.pdf', 'test');
    check(legacy_upload_file('/uploads/legacy.pdf') === realpath($fixture . '/uploads/legacy.pdf'), 'legacy upload resolves');
    check(legacy_upload_file('/uploads/../uploads-sibling/keep.txt') === null, 'traversal rejected');
    check(legacy_upload_file('/uploads-sibling/keep.txt') === null, 'sibling directory rejected');
    check(legacy_upload_file('/uploads/.htaccess') === null, 'upload security configuration cannot be deleted');
    if (@symlink($fixture . '/uploads-sibling/keep.txt', $fixture . '/uploads/escape.pdf')) {
        check(legacy_upload_file('/uploads/escape.pdf') === null, 'symlink outside uploads rejected');
    }
    $testBase = '/revision';
    check(public_media_url('/uploads/legacy.pdf') === '/revision/uploads/legacy.pdf', 'legacy media rendering respects subfolder installations');
    check(legacy_upload_file('/revision/uploads/legacy.pdf') !== null, 'subfolder upload resolves');
    check(legacy_upload_file('/revision-other/uploads/legacy.pdf') === null, 'base path boundary enforced');
    $testBase = '';

    file_put_contents($fixture . '/throttle-test.php', '<?php require __DIR__ . "/includes/bootstrap.php"; login_throttle_check((string) ($_GET["username"] ?? "admin")); echo "allowed";');
    file_put_contents($fixture . '/secure-session-test.php', '<?php $_SERVER["HTTPS"] = "on"; require __DIR__ . "/includes/bootstrap.php"; echo "secure";');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) {
        throw new RuntimeException($error);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $pipes = [];
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $fixture, $fixture . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $fixture . '/server.log', 'a'], 2 => ['file', $fixture . '/server.log', 'a']], $pipes, $fixture);
    if (!is_resource($server)) {
        throw new RuntimeException('Could not start test server');
    }
    fclose($pipes[0]);
    $cookies = [];
    $request = static function (string $path, ?array $data = null) use ($address, &$cookies): array {
        $cookie = [];
        foreach ($cookies as $key => $value) {
            $cookie[] = $key . '=' . $value;
        }
        $context = stream_context_create(['http' => [
            'method' => $data === null ? 'GET' : 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: " . implode('; ', $cookie),
            'content' => $data === null ? '' : http_build_query($data),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        ]]);
        $body = file_get_contents('http://' . $address . $path, false, $context);
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $m)) {
                $cookies[$m[1]] = $m[2];
            }
        }
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
        return [(int) $m[1], (string) $body, $http_response_header];
    };
    for ($i = 0; $i < 100; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if ($ready !== false) {
            fclose($ready);
            break;
        }
        usleep(50000);
    }
    foreach (['/.env', '/.htaccess', '/schema.sql', '/README.md', '/config/config.php', '/config/config.local.php', '/includes/bootstrap.php', '/keys/privatekey.txt', '/keys/ca.key', '/%6beys/privatekey.txt', '/%63onfig/config.php', '/%2e%2e/README.md', '/uploads/.htaccess', '/uploads/payload.php', '/.well-known/.env'] as $path) {
        check($request($path)[0] === 404, 'private route blocked: ' . $path);
    }
    check($request('/assets/css/app.css')[0] === 200, 'public asset served');
    $cookies = ['revisionhub_session' => 'attackerchosenidentifier12345'];
    [$status, $html, $headers] = $request('/install.php');
    check($cookies['revisionhub_session'] !== 'attackerchosenidentifier12345', 'strict sessions reject attacker-selected IDs');
    check($status === 200 && strpos($html, 'Installation token') !== false, 'setup authorization required');
    check(strpos(implode('\n', $headers), 'HttpOnly') !== false && strpos(implode('\n', $headers), 'SameSite=Lax') !== false, 'session cookie flags present');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $token);
    check($request('/install.php', ['install_token' => str_repeat('a', 64)])[0] === 403, 'setup authorization requires CSRF');
    check($request('/install.php', ['install_token' => 'wrong', 'csrf' => $token[1]])[0] === 403, 'wrong setup token rejected');
    check($request('/install.php', ['install_token' => str_repeat('a', 64), 'csrf' => $token[1]])[0] === 303, 'valid setup authorization succeeds');
    check($request('/install.php', ['action' => 'create_tables'])[0] === 403, 'table creation without CSRF rejected');
    check($request('/install.php', ['action' => 'create_admin', 'csrf' => 'wrong'])[0] === 403, 'admin creation with invalid CSRF rejected');
    check($request('/install.php', ['action' => 'create_tables', 'csrf' => ['array']])[0] === 403, 'array CSRF rejected');
    $settings['app']['install_token'] = '';
    file_put_contents($fixture . '/config/config.php', '<?php return ' . var_export($settings, true) . ';');
    check($request('/install.php')[0] === 403, 'empty setup token disables installation');
    $cookies = [];
    $secureHeaders = $request('/secure-session-test.php')[2];
    check(stripos(implode('\n', $secureHeaders), '; secure') !== false, 'HTTPS marks session cookies Secure');
    for ($i = 0; $i < 5; $i++) {
        $cookies = [];
        check($request('/throttle-test.php?username=' . ($i % 2 ? 'ADMIN' : 'admin'))[0] === 200, 'login attempt reserved across fresh sessions');
    }
    $cookies = [];
    check($request('/throttle-test.php?username=admin')[0] === 429, 'new session cannot bypass account throttle');
    for ($i = 0; $i < 15; $i++) {
        $cookies = [];
        check($request('/throttle-test.php?username=user' . $i)[0] === 200, 'separate account uses peer attempt budget');
    }
    check($request('/throttle-test.php?username=another')[0] === 429, 'rotating usernames cannot bypass peer throttle');
    $throttleFile = sys_get_temp_dir() . '/revisionhub-login-' . hash('sha256', $fixture) . '/attempts.json';
    $expired = json_decode(file_get_contents($throttleFile), true);
    foreach ($expired as &$bucket) {
        $bucket['until'] = time() - 1;
    }
    unset($bucket);
    file_put_contents($throttleFile, json_encode($expired));
    check($request('/throttle-test.php?username=admin')[0] === 200, 'expired throttle permits login again');
    echo "All security checks passed.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    remove_tree($fixture);
    $throttleDir = sys_get_temp_dir() . '/revisionhub-login-' . hash('sha256', $fixture);
    if (is_dir($throttleDir)) {
        remove_tree($throttleDir);
    }
}
