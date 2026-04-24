<?php

namespace App\Service;

use PDO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SessionManager
{
    private PDO $db;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $dbPath = $projectDir . '/knowledge/sessions.sqlite';
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
}
