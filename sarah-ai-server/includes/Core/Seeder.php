<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\EmailTemplateRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceTypeRepository;
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
        self::seedKnowledgeResourceTypes();
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

        // Trial: 30-day free access, basic agent only
        $repo->insertIfMissing(
            'Trial',
            'trial',
            30,
            [
                'max_messages'  => 500,
                'support_level' => 'community',
            ]
        );

        // Customer: permanent access (duration_days=0), all agents available
        $repo->insertIfMissing(
            'Customer',
            'customer',
            0,
            [
                'max_messages'  => -1,
                'support_level' => 'standard',
            ]
        );
    }

    private static function seedPlanAgents(): void
    {
        $planRepo  = new PlanRepository();
        $agentRepo = new AgentRepository();
        $repo      = new PlanAgentRepository();

        $trial    = $planRepo->findBySlug('trial');
        $customer = $planRepo->findBySlug('customer');

        // Trial: basic agent only
        if ($trial) {
            $agent = $agentRepo->findBySlug('gpt-4o-mini');
            if ($agent) {
                $repo->insertIfMissing((int) $trial['id'], (int) $agent['id']);
            }
        }

        // Customer: all active agents
        if ($customer) {
            foreach (['gpt-4o-mini', 'gpt-4o', 'o1'] as $slug) {
                $agent = $agentRepo->findBySlug($slug);
                if ($agent) {
                    $repo->insertIfMissing((int) $customer['id'], (int) $agent['id']);
                }
            }
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

    private static function seedKnowledgeResourceTypes(): void
    {
        $repo = new KnowledgeResourceTypeRepository();

        // enabled = 1 → available in UI;  enabled = 0 → hidden until re-enabled via DB
        $types = [
            ['text', 'Plain Text',    1, 10],
            ['link', 'Website Link',  1, 20],
            ['txt',  'Text File URL', 0, 30],  // disabled — use Plain Text or Website Link instead
            ['pdf',  'PDF File',      0, 40],  // disabled — not production-ready yet
            ['docx', 'Word Document', 0, 50],  // disabled — not production-ready yet
        ];

        foreach ($types as [$key, $label, $enabled, $order]) {
            $repo->seed($key, $label, $enabled, $order);
        }
    }

    private static function seedSettings(): void
    {
        $repo = new SettingsRepository();

        // Only set if not already configured — avoids overwriting admin changes.
        $defaults = [
            'platform_name'    => 'Sarah',
            'default_agent_slug' => 'gpt-4o-mini',
            'logging_enabled'  => '1',
            'openai_api_key'   => '',
            'platform_api_key' => 'www.BurstNET.com.au',
            'whmcs_api_url'    => '',
        ];

        foreach ($defaults as $key => $value) {
            if ($repo->get($key, '', 'platform') === '') {
                $repo->set($key, $value, 'platform');
            }
        }
    }
}
