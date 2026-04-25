<?php

namespace App\Service;

use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SessionManager
{
    private PDO $db;

    public function __construct() {
        // /knowledge is mounted as a volume in Docker
        $dbPath = '/knowledge/sessions.sqlite';
        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->initializeDatabase();
    }

    private function initializeDatabase(): void
    {
        $query = "
            CREATE TABLE IF NOT EXISTS user_sessions (
                chat_id TEXT PRIMARY KEY,
                active_objective TEXT,
                updated_at DATETIME
            )
        ";
        $this->db->exec($query);

        $historyQuery = "
            CREATE TABLE IF NOT EXISTS chat_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id TEXT NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->db->exec($historyQuery);

        // Safely add context_summary column if it doesn't exist
        try {
            $this->db->exec("ALTER TABLE user_sessions ADD COLUMN context_summary TEXT");
        } catch (\PDOException $e) {
            // Ignore error if column already exists
        }
    }

    public function getObjective(string $chatId): ?string
    {
        $stmt = $this->db->prepare("SELECT active_objective FROM user_sessions WHERE chat_id = :chat_id");
        $stmt->execute([':chat_id' => $chatId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['active_objective'] : null;
    }

    public function updateObjective(string $chatId, string $objective): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        
        $query = "
            INSERT INTO user_sessions (chat_id, active_objective, updated_at) 
            VALUES (:chat_id, :objective, :updated_at)
            ON CONFLICT(chat_id) DO UPDATE SET 
                active_objective = excluded.active_objective,
                updated_at = excluded.updated_at
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':chat_id' => $chatId,
            ':objective' => $objective,
            ':updated_at' => $now
        ]);
    }

    public function clearObjective(string $chatId): void
    {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE chat_id = :chat_id");
        $stmt->execute([':chat_id' => $chatId]);
    }

    public function getSummary(string $chatId): ?string
    {
        $stmt = $this->db->prepare("SELECT context_summary FROM user_sessions WHERE chat_id = :chat_id");
        $stmt->execute([':chat_id' => $chatId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['context_summary'] : null;
    }

    public function updateSummary(string $chatId, string $summaryText): void
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        
        $query = "
            INSERT INTO user_sessions (chat_id, context_summary, updated_at) 
            VALUES (:chat_id, :summary, :updated_at)
            ON CONFLICT(chat_id) DO UPDATE SET 
                context_summary = excluded.context_summary,
                updated_at = excluded.updated_at
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':chat_id' => $chatId,
            ':summary' => $summaryText,
            ':updated_at' => $now
        ]);
    }

    public function saveMessage(string $chatId, string $role, string $content): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO chat_history (chat_id, role, content)
            VALUES (:chat_id, :role, :content)
        ");
        $stmt->execute([
            ':chat_id' => $chatId,
            ':role' => $role,
            ':content' => $content
        ]);
    }

    public function getRecentHistory(string $chatId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT role, content 
            FROM chat_history 
            WHERE chat_id = :chat_id 
            ORDER BY id DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':chat_id', $chatId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_reverse($results);
    }
}
