<?php

namespace App\Agent;

interface AgentProfileInterface
{
    public function getSystemInstruction(?string $objective = null, ?string $summary = null): string;

    public function getFunctionDeclarations(): array;

    public function executeFunction(string $name, array|object $args, ?string $chatId = null): mixed;
}
