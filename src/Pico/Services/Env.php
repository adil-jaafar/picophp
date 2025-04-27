<?php

namespace PicoPHP\Services;

use PicoPHP\asSingleton;

class Env implements asSingleton
{
    public static $env_dirpath = "";
    private $data = []; // Stocke les variables d'environnement
    public function __invoke(string $key, $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function __construct()
    {
        // Stocker les variables dans $data pour un accès plus rapide
        $this->loadEnv(Env::$env_dirpath . '/.env');
    }

    private function loadEnv(string $filePath): void
    {
        if (!is_readable($filePath)) return;

        foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            // Ignorer les commentaires purs
            if ($line[0] === '#') continue;

            // Extraire clé et valeur (ignore les espaces autour de `=`)
            if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+?)\s*$/', $line, $matches)) {
                [$full, $key, $value] = $matches;

                // Si la valeur est entre guillemets, la nettoyer
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                } else {
                    // Supprimer les commentaires après un espace `#`
                    $value = explode(' #', $value, 2)[0];
                }

                // Charger dans $_ENV et putenv()
                $_ENV[$key] = trim($value);
                putenv("$key={$value}");
                $this->data[$key] = $this->parseValue($value);
            }
        }
    }


    private function parseValue(string $value): mixed
    {
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
            case 'empty':
                return '';
            default:
                return $value;
        }
    }
}
