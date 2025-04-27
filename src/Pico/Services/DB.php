<?php

namespace PicoPHP\Services;

use PDO;
use PDOException;
use PicoPHP\asSingleton;
use PicoPHP\Services\Env; // Ajout de Env

class Raw
{
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    // Indique que cette valeur ne doit pas être échappée
    public function isRaw(): bool
    {
        return true;
    }
}

class DB implements asSingleton
{
    private PDO $pdo;

    public function __construct(Env $env)
    {
        $host = $env("DB_HOST", "localhost");
        $port = $env("DB_PORT", "3306");
        $dbname = $env("DB_DATABASE", "");
        $user = $env("DB_USERNAME", "");
        $pass = $env("DB_PASSWORD", "");
        $charset = $env("DB_CHARSET", "utf8mb4");

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    public static function raw(string $value): Raw
    {
        return new Raw($value);
    }

    public static function now(): Raw
    {
        return DB::raw('NOW()');
    }

    // Exécuter une requête SQL (SELECT, INSERT, UPDATE, DELETE)
    public function query(string $sql, array $params = [], ?string $exec = null): bool|\PDOStatement
    {
        try {
            $expandedParams = [];

            foreach ($params as $key => $value) {

                $placeholder = ':' . ltrim($key, ':');

                if ($value instanceof Raw) {
                    // Sécurise la substitution avec une regex mot complet
                    $pattern = '/(?<![a-zA-Z0-9_])' . preg_quote($placeholder, '/') . '\b/';
                    $sql = preg_replace($pattern, $value->getValue(), $sql);
                } elseif (is_array($value)) {

                    if (count($value) === 0) {
                        throw new \Exception("Le tableau pour '$key' est vide — impossible d'exécuter IN().");
                    }

                    $placeholders = [];
                    foreach ($value as $index => $val) {
                        $paramName = $placeholder . '__' . $index;
                        $placeholders[] = $paramName;
                        $expandedParams[trim($paramName, ':')] = $val;
                    }

                    // Remplacement sécurisé dans le SQL
                    $pattern = '/(?<![a-zA-Z0-9_])' . preg_quote($placeholder, '/') . '\b/';
                    $sql = preg_replace($pattern, implode(', ', $placeholders), $sql);
                } else {
                    // valeur classique
                    $expandedParams[$placeholder] = $value;
                }
            }

            if ($exec) {
                $this->pdo->exec($exec);
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($expandedParams);
            return $stmt;
        } catch (PDOException $e) {
            die("Erreur SQL : " . $e->getMessage());
        }
    }

    // Récupérer une seule ligne
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->query($sql, $params)->fetch() ?: null;
    }

    // Récupérer plusieurs lignes
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    // Insérer une donnée et récupérer l'ID
    public function insert(string $table, array $data): int
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_map(fn($k) => ":$k", array_keys($data)));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    // Mettre à jour des données
    public function update(string $table, array $data, string $where, array $whereParams): bool
    {
        $setPart = implode(", ", array_map(fn($k) => "$k = :$k", array_keys($data)));
        $sql = "UPDATE $table SET $setPart WHERE $where";
        return (bool) $this->query($sql, array_merge($data, $whereParams));
    }

    // Supprimer une entrée
    public function delete(string $table, string $where, array $whereParams): bool
    {
        $sql = "DELETE FROM $table WHERE $where";
        return (bool) $this->query($sql, $whereParams);
    }

    // Gestion des transactions
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    // Récupérer l'instance PDO (si besoin)
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
