<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\EmailTemplateRepository;
use SarahAiServer\Infrastructure\PlanRepository;
use SarahAiServer\Infrastructure\SettingsRepository;

class Seeder
{
    /**
     * Seeds all baseline records.
     * Safe to call on every boot — all operations are idempotent.
     */
    public static function run(): void
    {
        self::seedAgents();
        self::seedPlans();
        self::seedEmailTemplates();
        self::seedSettings();
    }

    private static function seedAgents(): void
    {
        $repo = new AgentRepository();

        $repo->insertIfMissing(
            'Sarah Basic',
            'sarah-basic',
            'dummy',
            'A basic dummy agent that echoes responses. Used for development and testing.',
            ['response_mode' => 'echo', 'max_tokens' => 256]
        );

        $repo->insertIfMissing(
            'Sarah Pro',
            'sarah-pro',
            'dummy',
            'A pro dummy agent with simulated multi-turn context. Used for staging and integration tests.',
            ['response_mode' => 'simulate', 'max_tokens' => 1024, 'context_window' => 4]
        );
    }

    private static function seedPlans(): void
    {
        $repo = new PlanRepository();

        $repo->insertIfMissing(
            'Trial',
            'trial',
            14,
            [
                'max_sites'     => 1,
                'max_messages'  => 500,
                'agents'        => ['sarah-basic'],
                'support_level' => 'community',
            ]
        );
    }

    private static function seedEmailTemplates(): void
    {
        $repo = new EmailTemplateRepository();

        $repo->insertIfMissing(
            'welcome',
            'welcome',
            'Welcome to Sarah — Your AI Chat Platform',
            "Hello {{name}},\n\nWelcome to Sarah! Your account has been created successfully.\n\nHere are your login details:\n\nSite: {{site_url}}\nUsername: {{username}}\n\nYour trial period is active for {{trial_days}} days.\n\nIf you have any questions, feel free to reach out.\n\nBest regards,\nThe Sarah Team",
            ['name', 'site_url', 'username', 'trial_days']
        );
    }

    private static function seedSettings(): void
    {
        $repo = new SettingsRepository();

        // Only set if not already configured — avoids overwriting admin changes.
        $defaults = [
            'platform_name'       => 'Sarah',
            'trial_duration_days' => '14',
            'default_agent_slug'  => 'sarah-basic',
            'logging_enabled'     => '1',
        ];

        foreach ($defaults as $key => $value) {
            if ($repo->get($key, '', 'platform') === '') {
                $repo->set($key, $value, 'platform');
            }
        }
    }
}
