<?php
/**
 * Database Session Handler untuk Vercel & Multi-Instance Serverless
 * Menyimpan session login di database agar tidak logout sendiri saat serverless berpindah instance.
 */

class DatabaseSessionHandler implements SessionHandlerInterface {
    private ?PDO $pdo = null;

    private function getPdo(): ?PDO {
        if ($this->pdo === null) {
            try {
                $this->pdo = get_db();
            } catch (Throwable $e) {
                return null;
            }
        }
        return $this->pdo;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $db = $this->getPdo();
        if (!$db) return '';
        try {
            $stmt = $db->prepare("SELECT data FROM user_sessions WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $data = $stmt->fetchColumn();
            return $data !== false ? (string)$data : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool {
        $db = $this->getPdo();
        if (!$db) return false;
        try {
            $stmt = $db->prepare("
                REPLACE INTO user_sessions (id, data, last_activity) 
                VALUES (?, ?, ?)
            ");
            return $stmt->execute([$id, $data, time()]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool {
        $db = $this->getPdo();
        if (!$db) return false;
        try {
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        $db = $this->getPdo();
        if (!$db) return false;
        try {
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE last_activity < ?");
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return false;
        }
    }
}
