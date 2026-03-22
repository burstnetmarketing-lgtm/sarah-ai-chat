<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\EmailTemplateRepository;
use SarahAiServer\Infrastructure\PlanAgentRepository;
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
        self::seedPlanAgents();
        self::seedEmailTemplates();
        self::seedSettings();
    }

    private static function seedAgents(): void
    {
        $repo = new AgentRepository();

        // Archive legacy placeholder agents
        $repo->upsertBySlug('Sarah Basic (Legacy)', 'sarah-basic', 'dummy', 'Legacy placeholder — replaced by OpenAI agents.', [], 'inactive');
        $repo->upsertBySlug('Sarah Pro (Legacy)',   'sarah-pro',   'dummy', 'Legacy placeholder — replaced by OpenAI agents.', [], 'inactive');

        // OpenAI agents — ordered cheapest to most powerful
        $repo->upsertBySlug(
            'GPT-4o Mini',
            'gpt-4o-mini',
            'openai',
            'Fast and affordable. Best for simple Q&A and cost-sensitive deployments.',
            ['model' => 'gpt-4o-mini', 'max_tokens' => 1024, 'temperature' => 0.7, 'role' => 'customer support assistant', 'tone' => 'friendly', 'system_prompt' => '']
        );

        $repo->upsertBySlug(
            'GPT-4o',
            'gpt-4o',
            'openai',
            'Balanced and powerful. Recommended for most production sites.',
            ['model' => 'gpt-4o', 'max_tokens' => 2048, 'temperature' => 0.7, 'role' => 'customer support assistant', 'tone' => 'professional', 'system_prompt' => '']
        );

        $repo->upsertBySlug(
            'OpenAI o1',
            'o1',
            'openai',
            'Most capable reasoning model. Best for complex tasks and high-demand scenarios.',
            ['model' => 'o1', 'max_tokens' => 4096, 'temperature' => 1.0, 'role' => 'customer support assistant', 'tone' => 'professional', 'system_prompt' => '']
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
                'agents'        => ['gpt-4o-mini'],
                'support_level' => 'community',
            ]
        );
    }

    private static function seedPlanAgents(): void
    {
        $planRepo  = new PlanRepository();
        $agentRepo = new AgentRepository();
        $repo      = new PlanAgentRepository();

        $trial   = $planRepo->findBySlug('trial');
        $mini    = $agentRepo->findBySlug('gpt-4o-mini');

        if ($trial && $mini) {
            $repo->insertIfMissing((int) $trial['id'], (int) $mini['id']);
        }
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
            'default_agent_slug'  => 'gpt-4o-mini',
            'logging_enabled'     => '1',
            'openai_api_key'      => '',
            'platform_api_key'    => 'www.BurstNET.com.au',
        ];

        foreach ($defaults as $key => $value) {
            if ($repo->get($key, '', 'platform') === '') {
                $repo->set($key, $value, 'platform');
            }
        }
    }
}
