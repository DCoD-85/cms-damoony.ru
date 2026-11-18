<?php

declare(strict_types=1);

namespace Damoony\Data\Persona;

use Damoony\Core\Session\SessionInterface;
use Damoony\Data\Db\Connection;

/**
 * SSO-мост: авторизованный в persona пользователь получает локальную учётку
 * на www (по умолчанию), остальные алиасы — только по подтверждённому членству.
 */
final class PersonaBridge
{
    private const PERSONA_KEY = 'persona_id';
    private const LOCAL_KEY = 'user_id';

    public function __construct(
        private readonly Connection $db,
        private readonly SessionInterface $session,
        private readonly PersonaRepository $repo,
    ) {
    }

    public function sync(string $alias): void
    {
        if ($alias !== 'www') {
            return;
        }

        $personaId = (int) $this->session->get(self::PERSONA_KEY, 0);
        if ($personaId <= 0) {
            return;
        }

        $account = $this->repo->accountById($personaId);
        if ($account === [] || ($account['status'] ?? '') !== 'active') {
            return;
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE persona_id = :pid LIMIT 1');
        $stmt->execute(['pid' => $personaId]);
        $existing = $stmt->fetch();

        if (is_array($existing)) {
            $this->session->set(self::LOCAL_KEY, (int) $existing['id']);

            return;
        }

        $login = $this->uniqueLogin((string) $account['email']);
        $pdo->prepare(
            'INSERT INTO users (login, email, password_hash, role, status, persona_id)
             VALUES (:login, :email, :hash, :role, :status, :pid)',
        )->execute([
            'login' => $login,
            'email' => (string) $account['email'],
            'hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role' => 'user',
            'status' => 'active',
            'pid' => $personaId,
        ]);

        $this->session->set(self::LOCAL_KEY, (int) $pdo->lastInsertId());
    }

    private function uniqueLogin(string $email): string
    {
        $base = strtolower((string) strstr($email, '@', true));
        $base = preg_replace('/[^a-z0-9_]+/', '_', $base) ?? 'user';
        $base = trim($base, '_');
        if (strlen($base) < 3) {
            $base = 'user';
        }

        $login = substr($base, 0, 28);
        $candidate = $login;
        $i = 1;

        $stmt = $this->db->pdo()->prepare('SELECT 1 FROM users WHERE login = :login LIMIT 1');
        while (true) {
            $stmt->execute(['login' => $candidate]);
            if ($stmt->fetch() === false) {
                return $candidate;
            }
            $candidate = $login . '_' . (++$i);
        }
    }
}
