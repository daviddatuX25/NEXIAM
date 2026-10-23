<?php

namespace App\Ai\Skills;

final readonly class InstructionSkillDefinition
{
    /**
     * @param  list<string>  $allowedTools
     * @param  list<string>  $triggerKeywords
     */
    public function __construct(
        public string $name,
        public string $description,
        public int $version,
        public string $surface,
        public array $allowedTools,
        public array $triggerKeywords,
        public string $instructions,
        public string $contentHash,
    ) {}

    /**
     * @return array{
     *     name: string,
     *     description: string,
     *     version: int,
     *     surface: string,
     *     allowed_tools: list<string>,
     *     trigger_keywords: list<string>,
     *     instructions: string,
     *     content_hash: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'surface' => $this->surface,
            'allowed_tools' => $this->allowedTools,
            'trigger_keywords' => $this->triggerKeywords,
            'instructions' => $this->instructions,
            'content_hash' => $this->contentHash,
        ];
    }
}
