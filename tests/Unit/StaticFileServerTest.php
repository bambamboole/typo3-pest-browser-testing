<?php
declare(strict_types=1);

use Amp\Http\Server\Response as AmphpResponse;
use Bambamboole\Typo3Testing\Testing\StaticFileServer;

beforeEach(function (): void {
    $this->root = sys_get_temp_dir() . '/typo3-testing-' . bin2hex(random_bytes(4));
    mkdir($this->root, 0o755, true);
    mkdir($this->root . '/css');
    file_put_contents($this->root . '/css/main.css', 'body { color: red; }');
    file_put_contents($this->root . '/index.html', '<h1>Hi</h1>');
    file_put_contents($this->root . '/image.png', "\x89PNG\r\n\x1a\n");
    file_put_contents($this->root . '/danger.php', '<?php exit;');
    file_put_contents($this->root . '/unknown.xyz', 'whatever');
    $this->server = new StaticFileServer($this->root);
});

afterEach(function (): void {
    // Best-effort cleanup.
    @array_map('unlink', glob($this->root . '/*.*') ?: []);
    @array_map('unlink', glob($this->root . '/css/*') ?: []);
    @rmdir($this->root . '/css');
    @rmdir($this->root);
});

it('serves an existing CSS file with the right mime', function () {
    $response = $this->server->tryServe('/css/main.css');

    expect($response)->toBeInstanceOf(AmphpResponse::class);
    expect($response->getStatus())->toBe(200);
    expect($response->getHeader('content-type'))->toBe('text/css');
    expect($response->getHeader('content-length'))->toBe('20');
});

it('falls through (returns null) for paths that have no file on disk', function () {
    expect($this->server->tryServe('/nope.css'))->toBeNull();
    expect($this->server->tryServe('/does/not/exist.js'))->toBeNull();
});

it('refuses to serve .php files', function () {
    expect($this->server->tryServe('/danger.php'))->toBeNull();
    expect($this->server->tryServe('/DANGER.PHP'))->toBeNull();
});

it('rejects path traversal attempts', function () {
    expect($this->server->tryServe('/../etc/passwd'))->toBeNull();
    expect($this->server->tryServe('/css/../css/main.css'))->toBeNull();
    expect($this->server->tryServe('//double-slash'))->toBeNull();
});

it('falls through on the root path', function () {
    expect($this->server->tryServe('/'))->toBeNull();
    expect($this->server->tryServe(''))->toBeNull();
});

it('maps known extensions to the right mime type', function () {
    expect($this->server->tryServe('/image.png')->getHeader('content-type'))->toBe('image/png');
    expect($this->server->tryServe('/index.html')->getHeader('content-type'))->toBe('text/html; charset=utf-8');
});

it('defaults to application/octet-stream for unknown extensions', function () {
    expect($this->server->tryServe('/unknown.xyz')->getHeader('content-type'))->toBe('application/octet-stream');
});
