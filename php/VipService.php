<?php

declare(strict_types=1);

/**
 * Regras de negócio do VIP: criar/localizar jogador, aplicar pagamentos,
 * avisar o plugin e remover o status quando o prazo termina.
 */
final class VipService
{
    private PluginClient $plugin;

    public function __construct(
        private PDO $db,
        private array $config,
        ?PluginClient $plugin = null
    ) {
        $this->plugin = $plugin ?? new PluginClient(
            (string) $config['plugin']['url'],
            (string) $config['plugin']['token'],
            (int) $config['plugin']['timeout']
        );
    }

    /** Localiza o jogador pelo nick; cria o registro se for a primeira compra. */
    public function findOrCreateUser(string $username, string $email): User
    {
        $user = User::findByUsername($this->db, $username);
        if ($user === null) {
            return User::create($this->db, $username, $email);
        }
        if ($user->getEmail() !== $email) {
            $user->setEmail($email);
            $user->save();
        }
        return $user;
    }

    /** Status usado por status.php (inclui limpeza preguiçosa de VIP vencido). */
    public function status(string $username): array
    {
        $user = User::findByUsername($this->db, $username);
        if ($user === null) {
            return [
                'found' => false,
                'username' => User::normalizeUsername($username),
                'vip' => false,
                'days_remaining' => 0,
            ];
        }

        // Rede de segurança caso o cron ainda não tenha rodado: se a data já
        // passou, manda remover o status no servidor antes de responder.
        if ($user->hasVipFlag() && !$user->isVipActive()) {
            $reason = 'VIP vencido (verificação ao consultar status)';
            if ($this->plugin->setVip($user, false, null, $reason)) {
                $user->revokeVip();
                $user->save();
                $this->logEvent($user, 'REVOKE', null, $reason);
            } else {
                error_log('[vip] falha ao remover VIP vencido: ' . $this->plugin->getLastError());
            }
        }

        return array_merge(['found' => true], $user->toStatusArray());
    }

    /**
     * Processa a notificação de um pagamento do MercadoPago.
     *
     * - Idempotente: pagamentos já creditados não somam dias de novo.
     * - Os dias são gravados no banco na mesma transação do registro do pagamento.
     * - O aviso ao plugin acontece depois do commit; se falhar, a exceção faz
     *   o MercadoPago reenviar o webhook e apenas o aviso é repetido.
     */
    public function processPayment(array $payment): void
    {
        $mpId = trim((string) ($payment['id'] ?? ''));
        if ($mpId === '') {
            throw new InvalidArgumentException('Pagamento sem id.');
        }
        $paymentStatus = strtolower((string) ($payment['status'] ?? ''));
        $raw = json_encode($payment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
        $now = gmdate('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM payments WHERE mercado_pago_id = :id FOR UPDATE');
            $stmt->execute([':id' => $mpId]);
            $row = $stmt->fetch();

            if ($row === false) {
                $userId = $this->userIdFromExternalReference((string) ($payment['external_reference'] ?? ''));
                $user = $userId !== null ? User::findById($this->db, $userId) : null;
                if ($user === null) {
                    throw new RuntimeException('Jogador do pagamento não encontrado.');
                }

                $insert = $this->db->prepare(
                    'INSERT INTO payments
                        (mercado_pago_id, user_id, preference_id, status, amount, days_granted,
                         paid_at, raw_payload, created_at, updated_at)
                     VALUES
                        (:mp_id, :user_id, :pref, :status, :amount, 0,
                         :paid_at, :raw, :now, :now)'
                );
                $insert->execute([
                    ':mp_id'   => $mpId,
                    ':user_id' => $user->getId(),
                    ':pref'    => $payment['preference_id'] ?? null,
                    ':status'  => $paymentStatus,
                    ':amount'  => (float) ($payment['transaction_amount'] ?? 0),
                    ':paid_at' => $this->parseDate($payment['date_approved'] ?? null),
                    ':raw'     => $raw,
                    ':now'     => $now,
                ]);

                $paymentRowId = (int) $this->db->lastInsertId();
                $row = [
                    'id'             => (string) $paymentRowId,
                    'user_id'        => (string) $user->getId(),
                    'days_granted'   => 0,
                    'granted_at'     => null,
                    'notified_at'    => null,
                    'notify_attempts'=> 0,
                ];
            } else {
                $paymentRowId = (int) $row['id'];
                $user = User::findById($this->db, (int) $row['user_id']);
                if ($user === null) {
                    throw new RuntimeException('Jogador do pagamento não encontrado no banco.');
                }
                $this->db->prepare(
                    'UPDATE payments SET status = :status, raw_payload = :raw, updated_at = :now WHERE id = :id'
                )->execute([':status' => $paymentStatus, ':raw' => $raw, ':now' => $now, ':id' => $paymentRowId]);
            }

            $shouldGrant = $paymentStatus === 'approved' && empty($row['granted_at']);
            if ($shouldGrant) {
                $days = (int) $this->config['mercadopago']['days'];
                $user->grantVip($days);
                $user->save();
                $this->logEvent(
                    $user,
                    (int) $row['days_granted'] > 0 ? 'EXTEND' : 'GRANT',
                    $days,
                    'Pagamento MercadoPago #' . $mpId
                );
                $this->db->prepare(
                    'UPDATE payments SET days_granted = :days, granted_at = :now, updated_at = :now WHERE id = :id'
                )->execute([':days' => $days, ':now' => $now, ':id' => $paymentRowId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        if ($paymentStatus !== 'approved' || !empty($row['notified_at'])) {
            return;
        }

        $reason = 'Pagamento MercadoPago #' . $mpId . ($shouldGrant ? '' : ' (reenvio)');
        $this->notifyGrant($user, $paymentRowId, $reason);
    }

    /**
     * Cron principal: remove o VIP de quem a data já venceu.
     * Só altera o banco depois que o plugin confirmar a remoção, para que
     * uma falha temporária não deixe o jogador sem status no servidor.
     */
    public function revokeExpiredUsers(): array
    {
        $stmt = $this->db->query(
            'SELECT id FROM users
              WHERE vip_active = 1 AND vip_until IS NOT NULL AND vip_until <= UTC_TIMESTAMP()
              ORDER BY vip_until ASC
              LIMIT 100'
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $revoked = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $user = User::findById($this->db, (int) $id);
            if ($user === null) {
                continue;
            }
            $reason = 'VIP vencido em ' . $user->getVipUntil()?->format(DATE_ATOM);
            if (!$this->plugin->setVip($user, false, null, $reason)) {
                $failed++;
                error_log('[vip] falha ao remover ' . $user->getUsername() . ': ' . $this->plugin->getLastError());
                continue;
            }
            $user->revokeVip();
            $user->save();
            $this->logEvent($user, 'REVOKE', null, 'Expiração do prazo');
            $revoked++;
        }

        return ['revoked' => $revoked, 'failed' => $failed];
    }

    /** Cron auxiliar: repetir avisos ao plugin que falharam no webhook. */
    public function retryFailedNotifications(): array
    {
        $stmt = $this->db->query(
            "SELECT id, user_id, mercado_pago_id FROM payments
              WHERE status = 'approved' AND notified_at IS NULL AND notify_attempts < 5
              ORDER BY created_at ASC
              LIMIT 50"
        );
        $rows = $stmt->fetchAll();

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $user = User::findById($this->db, (int) $row['user_id']);
            if ($user === null) {
                continue;
            }
            if ($this->notifyGrant($user, (int) $row['id'], 'Reenvio pagamento #' . $row['mercado_pago_id'])) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /** Histórico para auditoria. */
    public function logEvent(User $user, string $action, ?int $days, string $reason): void
    {
        $this->db->prepare(
            'INSERT INTO vip_events (user_id, action, days, reason, created_at) VALUES (:user, :action, :days, :reason, :now)'
        )->execute([
            ':user'   => $user->getId(),
            ':action' => $action,
            ':days'   => $days,
            ':reason' => mb_substr($reason, 0, 255),
            ':now'    => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function notifyGrant(User $user, int $paymentRowId, string $reason): bool
    {
        $ok = $this->plugin->setVip($user, true, $user->getVipUntil(), $reason);
        if ($ok) {
            $this->db->prepare(
                'UPDATE payments
                    SET notified_at = :now, notify_attempts = 0, last_error = NULL, updated_at = :now
                  WHERE id = :id'
            )->execute([':now' => gmdate('Y-m-d H:i:s'), ':id' => $paymentRowId]);
            return true;
        }

        $error = $this->plugin->getLastError();
        $this->db->prepare(
            'UPDATE payments
                SET notify_attempts = notify_attempts + 1, last_error = :error, updated_at = :now
              WHERE id = :id'
        )->execute([':error' => mb_substr($error, 0, 1000), ':now' => gmdate('Y-m-d H:i:s'), ':id' => $paymentRowId]);
        error_log('[vip] falha ao ativar ' . $user->getUsername() . ' no plugin: ' . $error);
        return false;
    }

    /** O external_reference enviado ao MercadoPago é o id do usuário. */
    private function userIdFromExternalReference(string $reference): ?int
    {
        if (ctype_digit($reference) && (int) $reference > 0) {
            return (int) $reference;
        }
        if (preg_match('/user:(\d+)/', $reference, $match)) {
            return (int) $match[1];
        }
        return null;
    }

    /** Datas do MercadoPago (ISO 8601) para DATETIME UTC do MySQL. */
    private function parseDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
