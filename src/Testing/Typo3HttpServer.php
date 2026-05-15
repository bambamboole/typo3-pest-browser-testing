<?php
declare(strict_types=1);

namespace Bambamboole\Typo3Testing\Testing;

use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request as AmphpRequest;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response as AmphpResponse;
use Amp\Http\Server\SocketHttpServer;
use Nyholm\Psr7\ServerRequest;
use Pest\Browser\Contracts\HttpServer;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Http\Application;

final class Typo3HttpServer implements HttpServer
{
    private ?SocketHttpServer $server = null;
    private ?Throwable $lastThrowable = null;
    private string $host = '127.0.0.1';
    private int $port = 0;
    private string $baselineUrl;
    private string $baselineHost;
    private string $projectRoot;
    private StaticFileServer $staticFiles;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Typo3StateResetter $resetter,
    ) {
        $this->projectRoot = TestingPaths::projectRoot();
        $this->staticFiles = new StaticFileServer($this->projectRoot . '/public');

        $domain = Typo3Bootstrap::env('BASE_DOMAIN');
        if ($domain === '') {
            throw new RuntimeException(
                'BASE_DOMAIN env var is required. Set it in phpunit.xml <php><env name="BASE_DOMAIN" value="..."/></php> '
                . 'to match the host of the site you want to drive (must match the site config under config/sites/).'
            );
        }
        $this->baselineHost = $domain;
        $this->baselineUrl = 'http://' . $domain;
    }

    public function bootstrap(): void
    {
    }

    public function start(): void
    {
        if ($this->server !== null) {
            return;
        }

        if ($this->port === 0) {
            $this->port = $this->pickFreePort();
        }

        $server = SocketHttpServer::createForDirectAccess(new NullLogger());
        $server->expose("{$this->host}:{$this->port}");

        $handler = new ClosureRequestHandler(function (AmphpRequest $req): AmphpResponse {
            return $this->handleRequest($req);
        });

        $server->start($handler, new DefaultErrorHandler());
        $this->server = $server;
    }

    public function stop(): void
    {
        $this->server?->stop();
        $this->server = null;
    }

    public function rewrite(string $url): string
    {
        return sprintf('http://%s:%d%s', $this->host, $this->port, $url);
    }

    public function flush(): void
    {
        $this->lastThrowable = null;
        $this->resetter->reset();
    }

    public function lastThrowable(): ?Throwable
    {
        return $this->lastThrowable;
    }

    public function throwLastThrowableIfNeeded(): void
    {
        if ($this->lastThrowable instanceof Throwable) {
            throw $this->lastThrowable;
        }
    }

    public function url(): string
    {
        return sprintf('http://%s:%d', $this->host, $this->port);
    }

    private function handleRequest(AmphpRequest $req): AmphpResponse
    {
        $this->resetter->reset();

        $host = $this->baselineHost;
        $path = $req->getUri()->getPath();
        $query = $req->getUri()->getQuery();
        $uri = $this->baselineUrl . $path . ($query !== '' ? '?' . $query : '');

        $staticResponse = $this->staticFiles->tryServe($path);
        if ($staticResponse !== null) {
            return $staticResponse;
        }

        $serverParams = [
            'REQUEST_METHOD'  => $req->getMethod(),
            'HTTP_HOST'       => $host,
            'SERVER_NAME'     => $host,
            'SERVER_PORT'     => '80',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_URI'     => $path . ($query !== '' ? '?' . $query : ''),
            'REQUEST_SCHEME'  => 'http',
            'QUERY_STRING'    => $query,
            'REMOTE_ADDR'     => '127.0.0.1',
            'SCRIPT_NAME'     => '/index.php',
            'SCRIPT_FILENAME' => $this->projectRoot . '/public/index.php',
            'DOCUMENT_ROOT'   => $this->projectRoot . '/public',
        ];
        $_SERVER = array_merge($_SERVER, $serverParams);

        $rawBody = $req->getBody()->buffer();
        $contentType = $req->getHeader('content-type') ?? '';
        $parsedBody = null;
        $contentTypeBase = strtolower(trim(explode(';', $contentType)[0] ?? ''));
        if ($req->getMethod() === 'POST' && $contentTypeBase === 'application/x-www-form-urlencoded') {
            parse_str($rawBody, $parsedBody);
        }
        $_POST = is_array($parsedBody) ? $parsedBody : [];

        // Rewrite Referer (if present) from 127.0.0.1:<port> to baselineHost so
        // TYPO3's ReferrerEnforcer accepts it as same-origin.
        $headers = array_merge($req->getHeaders(), ['Host' => $host]);
        $localOrigin = sprintf('http://%s:%d', $this->host, $this->port);
        foreach (['referer', 'Referer'] as $h) {
            if (isset($headers[$h])) {
                $headers[$h] = array_map(
                    fn (string $v): string => str_replace($localOrigin, $this->baselineUrl, $v),
                    is_array($headers[$h]) ? $headers[$h] : [$headers[$h]],
                );
            }
        }
        $refererValue = $req->getHeader('referer') ?? '';
        if ($refererValue !== '') {
            $serverParams['HTTP_REFERER'] = str_replace($localOrigin, $this->baselineUrl, $refererValue);
        }

        $psr7 = new ServerRequest(
            method: $req->getMethod(),
            uri: $uri,
            headers: $headers,
            body: $rawBody,
            serverParams: $serverParams,
        );
        if (is_array($parsedBody)) {
            $psr7 = $psr7->withParsedBody($parsedBody);
        }
        // Surface cookies via getCookieParams() so middlewares (RequestTokenMiddleware,
        // session backends, ...) can read them.
        $cookieParams = [];
        foreach ($req->getCookies() as $name => $cookie) {
            $cookieParams[$name] = $cookie->getValue();
        }
        if ($cookieParams !== []) {
            $psr7 = $psr7->withCookieParams($cookieParams);
            $_COOKIE = $cookieParams;
        }

        try {
            $response = $this->container
                ->get(Application::class)
                ->handle($psr7);
        } catch (Throwable $e) {
            $this->lastThrowable = $e;
            return new AmphpResponse(
                status: 500,
                headers: ['Content-Type' => 'text/plain'],
                body: $e->getMessage(),
            );
        }

        $body = (string) $response->getBody();
        $contentTypeOut = $response->getHeaderLine('Content-Type');
        if (stripos($contentTypeOut, 'text/html') !== false) {
            $localOrigin = sprintf('http://%s:%d', $this->host, $this->port);
            $body = str_replace($this->baselineUrl, $localOrigin, $body);
        }

        return new AmphpResponse(
            status: $response->getStatusCode(),
            headers: $this->rewriteCookieDomain($response->getHeaders()),
            body: $body,
        );
    }

    /**
     * Rewrite outgoing headers so cookies and redirects target the right host:
     * - Set-Cookie: strip Domain=<baselineHost> so cookies attach to 127.0.0.1
     * - Location: rewrite absolute baselineUrl back to the local amphp origin
     *   so Playwright doesn't follow to a real DNS host
     *
     * @param array<string, array<int, string>> $headers
     * @return array<string, array<int, string>>
     */
    private function rewriteCookieDomain(array $headers): array
    {
        $localOrigin = sprintf('http://%s:%d', $this->host, $this->port);
        foreach ($headers as $name => $values) {
            if (strcasecmp($name, 'set-cookie') === 0) {
                $headers[$name] = array_map(
                    static fn (string $v): string => preg_replace('/;\s*domain=[^;]+/i', '', $v) ?? $v,
                    $values,
                );
                continue;
            }
            if (strcasecmp($name, 'location') === 0) {
                $headers[$name] = array_map(
                    fn (string $v): string => str_replace($this->baselineUrl, $localOrigin, $v),
                    $values,
                );
            }
        }
        return $headers;
    }

    private function pickFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        if ($sock === false) {
            throw new RuntimeException('Could not pick a free port');
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        if ($name === false) {
            throw new RuntimeException('Could not read free port from socket');
        }
        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
