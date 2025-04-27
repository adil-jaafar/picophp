<?php

namespace PicoPHP\Services;

class Files
{
    public function read(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Le fichier '$filePath' n'existe pas.");
        }
        return file_get_contents($filePath);
    }

    public function write(string $filePath, string $content): void
    {
        if (file_put_contents($filePath, $content) === false) {
            throw new \Exception("Impossible d'écrire dans le fichier '$filePath'.");
        }
    }

    public function writeOnce(string $filePath, string $content): bool
    {
        $directory = dirname($filePath);

        // Vérifie que le dossier existe, sinon tente de le créer
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Impossible de créer le dossier : $directory");
            }
        }

        // Tente d'ouvrir le fichier en mode 'x' (écriture exclusive, échoue si existe)
        $handle = @fopen($filePath, 'x');

        if ($handle === false) {
            // Fichier déjà existant
            return false;
        }

        fwrite($handle, $content);
        fclose($handle);
        return true;
    }
}
