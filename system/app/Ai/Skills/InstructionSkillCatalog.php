<?php

namespace App\Ai\Skills;

use RuntimeException;

final class InstructionSkillCatalog
{
    /**
     * @var array<string, InstructionSkillDefinition>|null
     */
    private ?array $skills = null;

    public function __construct(private readonly ?string $catalogDirectory = null) {}

    /**
     * @return list<InstructionSkillDefinition>
     */
    public function all(): array
    {
        return array_values($this->loadSkills());
    }

    public function get(string $name): ?InstructionSkillDefinition
    {
        return $this->loadSkills()[$name] ?? null;
    }

    /**
     * @return list<InstructionSkillDefinition>
     */
    public function forSurface(string $actorType): array
    {
        return array_values(array_filter(
            $this->loadSkills(),
            static fn (InstructionSkillDefinition $skill): bool => $skill->surface === 'any' || $skill->surface === $actorType,
        ));
    }

    public function renderTier1Catalog(string $actorType): string
    {
        $skills = $this->forSurface($actorType);
        $entries = array_map(
            static fn (InstructionSkillDefinition $skill): string => sprintf(
                '<skill name="%s" description="%s" />',
                htmlspecialchars($skill->name, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                htmlspecialchars($skill->description, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            ),
            $skills,
        );

        return "<available_skills>\n".implode("\n", $entries)."\n</available_skills>";
    }

    public function renderTier2ActiveSkill(?string $skillName, string $actorType): string
    {
        if ($skillName === null) {
            return '';
        }

        $skill = $this->get($skillName);

        if ($skill === null || ($skill->surface !== 'any' && $skill->surface !== $actorType)) {
            return '';
        }

        return sprintf(
            "<active_skill name=\"%s\" version=\"%d\">\n%s\n</active_skill>",
            htmlspecialchars($skill->name, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $skill->version,
            $skill->instructions,
        );
    }

    /**
     * @return array<string, InstructionSkillDefinition>
     */
    private function loadSkills(): array
    {
        if ($this->skills !== null) {
            return $this->skills;
        }

        $directory = $this->catalogDirectory ?? app_path('Ai/Skills/Catalog');
        $paths = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'SKILL.md') ?: [];
        sort($paths, SORT_STRING);
        $skills = [];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException(sprintf('Unable to read instruction skill file: %s', $path));
            }

            [$metadata, $instructions] = $this->parseSkillFile($contents, $path);
            $name = $metadata['name'] ?? '';
            $description = $metadata['description'] ?? '';

            if ($name === '' || $description === '' || trim($instructions) === '') {
                throw new RuntimeException(sprintf('Instruction skill file is missing required name, description, or markdown body: %s', $path));
            }

            if (isset($skills[$name])) {
                throw new RuntimeException(sprintf('Duplicate instruction skill name "%s" in %s.', $name, $path));
            }

            $version = $metadata['version'] ?? '1';

            if (filter_var($version, FILTER_VALIDATE_INT) === false || (int) $version < 1) {
                throw new RuntimeException(sprintf('Instruction skill version must be a positive integer: %s', $path));
            }

            $surface = $metadata['surface'] ?? 'any';

            if (! in_array($surface, ['staff', 'applicant', 'any'], true)) {
                throw new RuntimeException(sprintf('Instruction skill surface is invalid: %s', $path));
            }

            $skills[$name] = new InstructionSkillDefinition(
                name: $name,
                description: $description,
                version: (int) $version,
                surface: $surface,
                allowedTools: $metadata['allowed_tools'] ?? [],
                triggerKeywords: $metadata['trigger_keywords'] ?? [],
                instructions: trim($instructions),
                contentHash: hash('sha256', $contents),
            );
        }

        return $this->skills = $skills;
    }

    /**
     * @return array{array<string, string|list<string>>, string}
     */
    private function parseSkillFile(string $contents, string $path): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $lines = explode("\n", $contents);

        if (($lines[0] ?? null) !== '---') {
            throw new RuntimeException(sprintf('Instruction skill file must begin with YAML frontmatter: %s', $path));
        }

        $closingDelimiter = null;

        for ($index = 1, $lineCount = count($lines); $index < $lineCount; $index++) {
            if ($lines[$index] === '---') {
                $closingDelimiter = $index;
                break;
            }
        }

        if ($closingDelimiter === null) {
            throw new RuntimeException(sprintf('Instruction skill frontmatter is not closed: %s', $path));
        }

        $metadata = [];

        for ($index = 1; $index < $closingDelimiter; $index++) {
            $line = $lines[$index];

            if (trim($line) === '' || preg_match('/^\s*#/', $line) === 1) {
                continue;
            }

            if (preg_match('/^([a-z_]+):\s*(.*)$/', $line, $matches) !== 1) {
                throw new RuntimeException(sprintf('Invalid instruction skill frontmatter line in %s: %s', $path, $line));
            }

            $key = $matches[1];

            if (array_key_exists($key, $metadata)) {
                throw new RuntimeException(sprintf('Duplicate instruction skill frontmatter key "%s" in %s.', $key, $path));
            }

            $metadata[$key] = in_array($key, ['allowed_tools', 'trigger_keywords'], true)
                ? $this->parseList($matches[2], $lines, $index, $closingDelimiter)
                : $this->parseScalar($matches[2]);
        }

        return [$metadata, implode("\n", array_slice($lines, $closingDelimiter + 1))];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function parseList(string $value, array $lines, int &$index, int $closingDelimiter): array
    {
        $value = trim($value);

        if ($value !== '' && str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $value = substr($value, 1, -1);
        } elseif ($value === '') {
            $items = [];

            while ($index + 1 < $closingDelimiter && preg_match('/^\s+-\s*(.*)$/', $lines[$index + 1], $matches) === 1) {
                $index++;
                $item = $this->parseScalar($matches[1]);

                if ($item !== '') {
                    $items[] = $item;
                }
            }

            return $items;
        }

        if (trim($value) === '') {
            return [];
        }

        $items = [];

        foreach (str_getcsv($value, ',', '"', '\\') as $item) {
            $item = $this->parseScalar(trim($item));

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function parseScalar(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }

        return trim($value);
    }
}
