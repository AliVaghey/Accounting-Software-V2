<?php

namespace App;

use Laravel\Boost\Contracts\SupportsGuidelines;
use Laravel\Boost\Contracts\SupportsMcp;
use Laravel\Boost\Contracts\SupportsSkills;
use Laravel\Boost\Install\Agents\Agent;
use Laravel\Boost\Install\Enums\McpInstallationStrategy;
use Laravel\Boost\Install\Enums\Platform;

class GapCode extends Agent implements SupportsMcp, SupportsGuidelines, SupportsSkills
{
    public function name(): string
    {
        return 'gap_code';
    }

    public function displayName(): string
    {
        return 'GapCode';
    }

    public function systemDetectionConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Darwin, Platform::Linux => [
                'command' => 'command -v claude',
            ],
            Platform::Windows => [
                'command' => 'cmd /c where claude 2>nul',
            ],
        };
    }

    public function projectDetectionConfig(): array
    {
        return [
            'paths' => ['.agents'],
            'files' => ['AGENTS.md'],
        ];
    }

    public function mcpInstallationStrategy(): McpInstallationStrategy
    {
        return McpInstallationStrategy::FILE;
    }

    public function mcpConfigPath(): string
    {
        return config('boost.agents.gap_code.mcp_config_path', '.mcp.json');
    }

    public function guidelinesPath(): string
    {
        return config('boost.agents.gap_code.guidelines_path', 'AGENTS.md');
    }

    public function skillsPath(): string
    {
        return config('boost.agents.gap_code.skills_path', '.agents/skills');
    }
}
