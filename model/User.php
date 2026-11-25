<?php

declare(strict_types=1);

/**
 * Entidade do jogador (active record).
 *
 * Guarda id, username, uuid, e-mail e a situação do VIP. Toda a lógica de
 * "quantos dias restam" e "somar/extender dias" vive aqui; efeitos externos
 * (avisar o plugin, gravar pagamento) ficam no VipService.
 */
final class User
{
    private ?int $id = null;
    private string $username;
    private string $email;
    private ?string $uuid = null;
    private bool $vipActive = false;
    private ?DateTimeImmutable $vipUntil = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(private PDO $db)
    {
        $now = self::now();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** Nicks do Minecraft não diferenciam maiúsculas/minúsculas. */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    public static function isValidUsername(string $username): bool
    {
        return (bool) preg_match('/^[a-z0-9_]{3,16}$/', self::normalizeUsername($username));
    }

    public static function findByUsername(PDO $db, string $username): ?self
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => self::normalizeUsername($username)]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($db, $row);
    }

    public static function findById(PDO $db, int $id): ?self
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::fromRow($db, $row);
    }

    public static function create(PDO $db, string $username, string $email, ?string $uuid = null): self
    {
        $now = self::now()->format('Y-m-d H:i:s');
        $stmt = $db->prepare(
            'INSERT INTO users (username, email, uuid, vip_active, vip_until, created_at, updated_at)
             VALUES (:username, :email, :uuid, 0, NULL, :now, :now)'
        );
        $stmt->execute([
            ':username' => self::normalizeUsername($username),
            ':email'    => $email,
            ':uuid'     => $uuid,
            ':now'      => $now,
        ]);

        return self::findById($db, (int) $db->lastInsertId())
            ?? throw new RuntimeException('Não foi possível carregar o usuário recém-criado.');
    }

    private static function fromRow(PDO $db, array $row): self
    {
        $user = new self($db);
        $user->id         = (int) $row['id'];
        $user->username   = (string) $row['username'];
        $user->email      = (string) $row['email'];
        $user->uuid       = $row['uuid'] !== null ? (string) $row['uuid'] : null;
        $user->vipActive  = (bool) $row['vip_active'];
        $user->vipUntil   = $row['vip_until'] !== null
            ? new DateTimeImmutable((string) $row['vip_until'], new DateTimeZone('UTC'))
            : null;
        $user->createdAt  = new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC'));
        $user->updatedAt  = new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC'));
        return $user;
    }

    // ------------------------------------------------------------------
    // Getters
    // ------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getVipUntil(): ?DateTimeImmutable
    {
        return $this->vipUntil;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Flag salva no banco, antes de considerar a data de expiração. */
    public function hasVipFlag(): bool
    {
        return $this->vipActive;
    }

    /** Verdadeiro somente se o VIP está ativo E ainda não venceu. */
    public function isVipActive(): bool
    {
        return $this->vipActive
            && $this->vipUntil !== null
            && $this->vipUntil > self::now();
    }

    /** Dias inteiros restantes (arredonda para cima para não mostrar 0 no último dia). */
    public function getRemainingDays(): int
    {
        if (!$this->isVipActive()) {
            return 0;
        }
        $seconds = $this->vipUntil->getTimestamp() - self::now()->getTimestamp();
        return (int) max(1, ceil($seconds / 86400));
    }

    public function getRemainingTime(): array
    {
        if (!$this->isVipActive()) {
            return ['days' => 0, 'hours' => 0, 'minutes' => 0];
        }
        $seconds = max(0, $this->vipUntil->getTimestamp() - self::now()->getTimestamp());
        return [
            'days'    => intdiv($seconds, 86400),
            'hours'   => intdiv($seconds % 86400, 3600),
            'minutes' => intdiv($seconds % 3600, 60),
        ];
    }

    // ------------------------------------------------------------------
    // Alterações de estado
    // ------------------------------------------------------------------

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setUuid(?string $uuid): void
    {
        $this->uuid = $uuid;
    }

    /**
     * Concede (ou renova) VIP por N dias.
     * Se ainda sobram dias, os novos dias são somados ao fim do período atual,
     * é assim que o pagamento mensal renova sem perder tempo restante.
     *
     * @return DateTimeImmutable nova data de expiração (UTC)
     */
    public function grantVip(int $days): DateTimeImmutable
    {
        if ($days < 1) {
            throw new InvalidArgumentException('A quantidade de dias deve ser positiva.');
        }

        $now = self::now();
        $base = ($this->vipUntil !== null && $this->vipUntil > $now) ? $this->vipUntil : $now;
        $this->vipUntil = $base->modify("+{$days} days");
        $this->vipActive = true;
        return $this->vipUntil;
    }

    /** Remove o VIP (usado quando o prazo acaba). */
    public function revokeVip(): void
    {
        $this->vipActive = false;
        $this->vipUntil = null;
    }

    public function save(): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
                SET email = :email, uuid = :uuid, vip_active = :active,
                    vip_until = :until, updated_at = :updated
              WHERE id = :id'
        );
        $stmt->execute([
            ':email'  => $this->email,
            ':uuid'   => $this->uuid,
            ':active' => $this->vipActive ? 1 : 0,
            ':until'  => $this->vipUntil?->format('Y-m-d H:i:s'),
            ':updated' => self::now()->format('Y-m-d H:i:s'),
            ':id'     => $this->id,
        ]);
        $this->updatedAt = self::now();
    }

    /** Dados públicos devolvidos por status.php. */
    public function toStatusArray(): array
    {
        return [
            'username'        => $this->username,
            'vip'             => $this->isVipActive(),
            'days_remaining'  => $this->getRemainingDays(),
            'time_remaining'  => $this->getRemainingTime(),
            'expires_at'      => $this->vipUntil?->format(DATE_ATOM),
            'member_since'    => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
