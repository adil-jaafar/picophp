<?php

namespace PicoPHP\Services;

use PicoPHP\asSingleton;


class Response implements asSingleton
{
    
    public static $viewsPath;

    private string $content = "";
    private int $statusCode = 200;
    private string $contentType = "text/plain";
    private array $headers = [];
    private array $cookies = [];
    private bool $isStreaming = false;
    private ?string $filePath = null;
    private ?int $chunkSize = null;

    public function __invoke($status = 200)
    {
        $this->status($status);
        return $this;
    }

    public function template(string $view, array $data = []): self
    {
        $this->content = (function () use ($view, $data) {
            extract($data);
            ob_start();
            include Response::$viewsPath . "/{$view}.php";
            return ob_get_clean();
        })();
        $this->contentType = "text/html; charset=UTF-8";
        return $this;
    }

    // Réponse JSON
    public function json(array $data): self
    {
        $this->content = json_encode($data);
        $this->contentType = "application/json";
        return $this;
    }

    // Réponse texte brut
    public function text(string $text): self
    {
        $this->content = $text;
        $this->contentType = "text/plain";
        return $this;
    }

    // Réponse HTML
    public function html(string $html): self
    {
        $this->content = $html;
        $this->contentType = "text/html";
        return $this;
    }

    // Définir un statut HTTP
    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    // Ajouter un en-tête HTTP
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // Ajouter un cookie
    public function cookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = "/",
        string $domain = "",
        bool $secure = false,
        bool $httponly = false,
        string $samesite = "Lax"
    ): self {
        $this->cookies[] = compact("name", "value", "expires", "path", "domain", "secure", "httponly", "samesite");
        return $this;
    }

    // Redirection HTTP
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->statusCode = $statusCode;
        $this->headers["Location"] = $url;
        return $this;
    }

    // Gestion du cache (Etag, Expires, Cache-Control)
    public function cache(int $seconds): self
    {
        $this->header("Cache-Control", "public, max-age=$seconds");
        return $this;
    }

    public function etag(string $etag): self
    {
        $this->header("ETag", $etag);
        return $this;
    }

    // Gestion des erreurs
    public function error(int $code, string $message = "Erreur"): self
    {
        return $this->status($code)->json(["error" => $message]);
    }

    // Envoyer un fichier en téléchargement
    public function file(string $path, ?string $filename = null, ?bool $isAttachment = true): self
    {
        if (!file_exists($path)) {
            return $this->error(404, "Fichier non trouvé");
        }
        $this->filePath = $path;
        $this->contentType = mime_content_type($path) ?: "application/octet-stream";
        if ($isAttachment) $this->header("Content-Disposition", "attachment; filename=\"" . ($filename ?? basename($path)) . "\"");
        return $this;
    }

    // Streaming de fichiers volumineux
    public function streamFile(string $path, int $chunkSize = 4096): self
    {
        if (!file_exists($path)) {
            return $this->error(404, "Fichier non trouvé");
        }
        $this->filePath = $path;
        $this->chunkSize = $chunkSize;
        $this->isStreaming = true;
        $this->contentType = mime_content_type($path) ?: "application/octet-stream";
        return $this;
    }

    // Envoi de la réponse
    public function send(): void
    {
        http_response_code($this->statusCode);
        header("Content-Type: {$this->contentType}");

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie['name'],
                $cookie['value'],
                $cookie['expires'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
            header(
                "Set-Cookie: {$cookie['name']}={$cookie['value']}; Path={$cookie['path']}; SameSite={$cookie['samesite']}" .
                    ($cookie['secure'] ? "; Secure" : "") .
                    ($cookie['httponly'] ? "; HttpOnly" : "")
            );
        }

        // 📂 Gestion de l'envoi de fichiers
        if ($this->filePath !== null) {
            if ($this->isStreaming) {
                $this->streamFileContent();
            } else {
                readfile($this->filePath);
            }
        } else {
            echo $this->content;
        }
    }

    // Méthode pour envoyer un fichier en streaming
    private function streamFileContent(): void
    {
        $handle = fopen($this->filePath, "rb");
        if ($handle === false) {
            http_response_code(500);
            echo "Erreur de lecture du fichier.";
            return;
        }

        while (!feof($handle)) {
            echo fread($handle, $this->chunkSize);
            flush(); // ⚡ Évite l'accumulation en mémoire
        }
        fclose($handle);
    }
}
