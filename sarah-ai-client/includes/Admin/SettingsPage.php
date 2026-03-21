<?php

declare(strict_types=1);

namespace SarahAiClient\Admin;

use SarahAiClient\Infrastructure\SettingsRepository;

class SettingsPage
{
    private SettingsRepository $repo;

    public function __construct(SettingsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('sarah_ai_client_settings', 'sarah_ai_client_options', [
            'sanitize_callback' => [$this, 'sanitize'],
        ]);

        add_settings_section(
            'sarah_ai_client_general',
            __('Chat Widget', 'sarah-ai-client'),
            '__return_false',
            'sarah-ai-client'
        );

        // Phase 2–3: add fields here (button label, welcome message, etc.)
    }

    public function sanitize(mixed $input): array
    {
        $clean = [];
        // Phase 2–3: sanitize fields here
        return $clean;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('sarah_ai_client_settings');
                do_settings_sections('sarah-ai-client');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
