<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$input = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$directory = realpath($input['directory']);
if (! $directory || ! str_starts_with($directory, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'warmindo-concurrency-')) {
    throw new RuntimeException('Concurrency worker requires an isolated temporary database.');
}
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$app->detectEnvironment(fn (): string => 'testing');
config([
    'app.env' => 'testing', 'app.debug' => false, 'app.url' => 'http://localhost',
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => $directory.'/database.sqlite',
    'database.connections.sqlite.url' => null,
    'session.driver' => 'file', 'session.files' => $directory.'/sessions',
    'cache.default' => 'array', 'logging.default' => 'single', 'logging.channels.single.path' => $directory.'/worker.log',
]);
DB::purge('sqlite');
$cookies = $input['cookies'] ?? [];
$kernel = $app->make(Kernel::class);
$send = function (string $method, string $path, array $data) use ($kernel, &$cookies): array {
    $request = Request::create('http://localhost'.$path, $method, $data, $cookies, server: ['HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($request);
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    $errors = session('errors');
    $result = ['status' => $response->getStatusCode(), 'location' => $response->headers->get('Location'), 'body' => $response->isRedirection() ? null : $response->getContent(), 'session_errors' => is_array($errors) ? $errors : ($errors?->all() ?? [])];
    $kernel->terminate($request, $response);

    return $result;
};
if (isset($input['login'])) {
    $login = $send('POST', '/login', $input['login']);
    if ($login['status'] !== 302) {
        throw new RuntimeException('Worker login failed: '.json_encode($login));
    }
}
if (isset($input['review'])) {
    $review = $send('POST', '/menu/'.$input['qr_token'].'/checkout/review', $input['review']);
    if ($review['status'] !== 302 || ! $review['location']) {
        throw new RuntimeException('Worker review failed: '.json_encode($review));
    }
    $input['payload']['checkout_token'] = basename($review['location']);
}
if (($input['mode'] ?? '') === 'prepare') {
    echo json_encode(['cookies' => $cookies, 'token' => $input['payload']['checkout_token']], JSON_THROW_ON_ERROR);
    exit;
}
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 30;
while (! is_file($input['gate'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrency start barrier timed out.');
    }
    usleep(1000);
}
$start = microtime(true);
$result = $send($input['method'] ?? 'POST', $input['path'] ?? '/menu/'.$input['qr_token'].'/checkout', $input['payload']);
echo json_encode($result + ['token' => $input['payload']['checkout_token'] ?? null, 'start' => $start, 'end' => microtime(true)], JSON_THROW_ON_ERROR);
