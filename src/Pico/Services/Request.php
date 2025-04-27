<?php

namespace PicoPHP\Services;

use PicoPHP\asSingleton;

class Request implements asSingleton
{
    private array $query;      // GET parameters
    private array $body;       // POST or JSON parameters
    private array $headers;    // HTTP Headers
    private array $cookies;    // Cookies
    private array $files;      // Uploaded files
    private string $method;    // HTTP Method
    private string $path;      // URL path
    private string $rawBody;   // Raw body content
    private string $baseUrl;   // Base URL
    private string $requestUri; // Request URI
    private array $server;     // $_SERVER variables

    public function __construct()
    {
        $this->server = $_SERVER;
        $this->method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        $this->baseUrl = $this->getBaseUrl();
        $this->requestUri = $this->getRequestUri();
        $this->query = $this->sanitize($_GET);
        $this->cookies = $_COOKIE;
        $this->headers = getallheaders() ?: [];
        $this->files = $_FILES;
        $this->rawBody = file_get_contents("php://input");

        // Détecter JSON et remplir $this->body
        if ($this->isJson()) {
            $this->body = json_decode($this->rawBody, true) ?? [];
        } else {
            if (in_array($this->method, ['PUT', 'DELETE', 'PATCH'])) {
                $this->body = [];
                parse_str($this->rawBody, $this->body);
            } else {
                $this->body = $this->sanitize($_POST);
            }
        }
    }

    // Récupérer la méthode HTTP
    public function method(): string
    {
        return $this->method;
    }

    // Récupérer le chemin (URI sans domaine)
    public function path(): string
    {
        return $this->requestUri;
    }

    // Récupérer l'URL complète avec paramètres
    public function fullUrl(): string
    {
        return $this->baseUrl . $_SERVER['REQUEST_URI'];
    }

    // Vérifier si la requête contient du JSON
    public function isJson(): bool
    {
        return str_contains($this->header("Content-Type") ?? "", "application/json");
    }

    // Récupérer un paramètre GET
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    // Récupérer un paramètre POST ou JSON
    public function body(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    // Récupérer tout le JSON
    public function json(): array
    {
        return $this->body;
    }

    // Récupérer le corps brut (ex: JSON, XML, texte)
    public function raw(): string
    {
        return $this->rawBody;
    }

    // Récupérer un header HTTP
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    // Récupérer un cookie
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function hasHeader(string $key): bool
    {
        return isset($this->headers[$key]);
    }
    public function hasCookie(string $key): bool
    {
        return isset($this->cookies[$key]);
    }

    // Récupérer l’adresse IP du client
    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // Récupérer l’agent utilisateur
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    // Vérifier si la requête est AJAX
    public function isAjax(): bool
    {
        return strtolower($this->header("X-Requested-With") ?? '') === 'xmlhttprequest';
    }

    // Vérifier si la requête est GET, POST, PUT, DELETE, PATCH
    public function isGet(): bool
    {
        return $this->method === 'GET';
    }
    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
    public function isPut(): bool
    {
        return $this->method === 'PUT';
    }
    public function isDelete(): bool
    {
        return $this->method === 'DELETE';
    }
    public function isPatch(): bool
    {
        return $this->method === 'PATCH';
    }

    // Vérifier si un fichier a été uploadé
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    // Récupérer un fichier uploadé
    public function getFile(string $key): ?array
    {
        return $this->hasFile($key) ? $this->files[$key] : null;
    }

    // Récupérer tous les fichiers uploadés
    public function allFiles(): array
    {
        return $this->files;
    }

    // Sauvegarder un fichier uploadé
    public function storeFile(string $key, string $destination): ?string
    {
        if (!$this->hasFile($key)) return null;
        $file = $this->getFile($key);
        $filename = time() . "_" . basename($file['name']);
        $path = rtrim($destination, '/') . '/' . $filename;
        return move_uploaded_file($file['tmp_name'], $path) ? $path : null;
    }

    // Nettoyage des entrées (protection XSS)
    private function sanitize(array $data): array
    {
        return array_map(fn($item) => is_string($item) ? htmlspecialchars($item, ENT_QUOTES, 'UTF-8') : $item, $data);
    }

    // Récupérer l'URL de base (ex: `http://localhost/projet`)
    protected function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
        return rtrim("$protocol://$host" . dirname($_SERVER['SCRIPT_NAME']), '/');
        //return rtrim("$protocol://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['SCRIPT_NAME']), '/');
    }

    // Récupérer l’URI de la requête (ex: `/api/users/1`)
    protected function getRequestUri(): string
    {
        $uri = strtok($_SERVER['REQUEST_URI'], '?'); // Supprime les paramètres GET
        return ltrim(str_replace($this->baseUrl, '', $uri), '/');
    }
}
