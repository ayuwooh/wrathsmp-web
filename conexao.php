<?php

declare(strict_types=1);

/** Conexão PDO com MySQL. Exige as extensões pdo e pdo_mysql. */
final class Conexao
{
    private ?PDO $connection = null;

    public function __construct(private array $config)
    {
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['name']
            );

            $this->connection = new PDO($dsn, $this->config['user'], $this->config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return $this->connection;
    }
}
