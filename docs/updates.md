1000 Added silent and verbose logging modes to deploy script.
1001 Fixed duplicate slug declaration in deploy script.
1002 Added PowerShell and batch deploy wrappers.
1003 Created agent task documentation standard.
1004 Added version update command via npm run update.
1005 Made sidebar Create New button label and URL dynamic via settings.
1006 Added npm run commit helper using last line of updates.md.
1007 Added npm run push helper script.
1008 Created commit.txt and updated commit.js to read from it.
1009 Replaced update.js with per-plugin update-project-name.js script.
1010 Fixed Persian digit input in menu.js on Windows.
1011 Reorganized root — moved ai, tasks, deploy-configs, updates.md into docs/.
1012 Added AGENTS.md, WORKFLOW.md, setup-plan.md and supporting docs.
1013 Updated setup-plan.md to reset counter and updates.md after setup.
1014 Strengthened commit.txt rule in AGENTS.md to be explicit and non-optional.
1015 Created docs/technical/deploy and docs/technical/publish folders.
1016 Moved deploy-configs into docs/technical/deploy and updated all references.
1017 Removed deploy-configs subfolder, configs now directly in docs/technical/deploy/.
1018 Set up publish folder structure with gitignored auth.js, zip/, and log files.
1019 Moved baseUrl from project config to auth.js.
1020 Added build.js and publish.js scripts with cross-platform ZIP and update server support.
1021 Replaced archiver with tar in build.js, removed npm dependencies and node_modules.
1022 Renamed build.js to zip.js and excluded it from the menu.
1023 Added interactive plugin selection menu to zip.js and publish.js.
1024 Added open ZIP folder prompt after build in zip.js.
1025 Added Persian digit support to all prompts in zip.js.
1026 Added _publish-config.js helper to prompt and save missing plugin slug and guid.
1027 Filled auth.js with real credentials and removed it from gitignore.
1028 Excluded publish.js from the menu.
1029 Restored zip.js to the menu.
1030 Added --silent flag to zip.js for use by publish.js without prompts.
1031 Added projectGuid validation before publish in publish.js.
1032 Restored publish.js to the menu.
1033 Fixed ensureConfigs to only ask for projectGuid in publish.js, not zip.js.
1034 Rewrote zip.js to find plugins directly from root, removed publish config dependency.
1035 Moved project configs to projects/ subfolder, zip.js and publish.js read from there.
1036 Fixed zip.js to use filename as slug fallback when pluginSlug is empty.
1037 Fixed Persian digit support in open folder prompt in zip.js.
1038 Added stdin.resume before askOpenFolder readline to fix Persian input.
1039 Added stdin.resume in menu.js after rl.close so child scripts can read input.
1040 Added file copy counter and ZIP creation progress output to zip.js.
1041 Replaced plain progress with colorful bar, spinner, and delays in zip.js.
1042 Removed open folder prompt in zip.js, always opens automatically after build.
1043 Fixed sleep() in zip.js — replaced Atomics.wait with busy-wait for Node.js v24.
1044 Fixed explorer exit code crash in zip.js — replaced execSync with spawn detached.
1045 Replaced push.js with git.js — menu with Pull and Push options.
1046 Added stdin.resume before readline in git.js and zip.js to fix input from menu.
1047 Switched git.js to raw mode keypress — no Enter needed.
1048 Replaced execSync with spawnSync in menu.js to preserve TTY for child scripts.
1049 Replaced readline with raw mode readKey in menu.js to fix child script input.
1050 Scripts now open in a new cmd window from menu — fully independent TTY.
1051 Fixed new cmd window opening in System32 — added cwd to spawn in menu.js.
1052 Added Run Again prompt at end of all 6 scripts via shared _prompt.js helper.
1053 Rolled back 1052; zip.js now asks open folder with raw mode keypress instead.
1054 Added open deploy folder prompt at end of deploy.js with raw mode keypress.
1055 Removed Mendy template; replaced with Bootstrap 5 placeholder admin page.
1056 Added Menu Manager view with Bootstrap 5 table and add/toggle/delete actions.
1057 Replaced menu table with accordion UI supporting parent-child menu items.
1058 Added up/down reorder buttons for child menu items with ID-based sort order.
0003 Refactored project-name template into dual-plugin structure: sarah-ai-client and sarah-ai-server.
0004 Removed admin dashboard from sarah-ai-client; plugin is now a clean frontend chat widget base.
0005 Added minimal settings page to sarah-ai-client under Settings menu for future widget configuration.
0006 Restored full React admin dashboard in sarah-ai-client alongside the settings page.
0007 Fixed disabled Menu Manager and Log buttons; removed logout from Topbar; fixed sidebar brand.
0008 Removed Settings page from sarah-ai-client.
0009 Added Settings menu item to sidebar seed; fixed missing seedDefaults in MenuRepository.
0010 Added SettingsController REST API and Settings admin page with widget enable/disable toggle.
0011 Built chat widget React components: ChatWidget, LauncherButton, ChatWindow, Header, MessageArea, InputBox.
0012 Added standalone widget CSS with launcher, chat window, header, message area, input, and mobile styles.
0013 Updated vite.config.js to dual output: app.js/css for admin, widget.js/css for frontend widget.
0014 Built all assets successfully; widget bundle is 2.7 KB, admin bundle is 95 KB.
0015 Fixed widget not loading on frontend — added type="module" to script tag via script_loader_tag filter.
0016 Phase 1.5 UI polish — welcome state, launcher cleanup, input contrast, layout improvements.
0017 Phase 2 — mock chat interaction, typing indicator, quick questions admin page and widget buttons.
0018 Phase 3 — appearance settings with draft/publish system, live preview, dynamic CSS custom properties.
0019 Fixed widget positioning gap and added mobile fullscreen mode; fixed PHP fatal error in enqueueWidget.
0020 Expanded appearance settings to 6 tabs with per-element color/size controls and full-height sticky live preview.
0021 Fixed Publish and Discard buttons by adding SettingsTable::create() to Plugin::boot() so draft columns exist.
0022 Added Reset to Default button with navy+yellow brand theme; added /appearance/reset endpoint.
0023 Rebuilt sarah-ai-server admin UI with Techmin-inspired layout: fixed sidebar (navy+yellow), topbar, content area, footer.
0024 Replaced sarah-ai-server admin UI with actual Techmin template SCSS + adapted React layout components.
0025 Phase 4.1 — Multi-tenant DB foundations, repositories, and seeder (tenants, sites, tokens, agents, plans, subscriptions, email templates, usage logs).
0026 Phase 4.2 — Knowledge base foundation with extensibility patches (open resource_type, processed_content bridge, content_group, lifecycle vs processing state separation).
0027 Phase 4.3 — Admin provisioning, dual-credential model (AccountKey + SiteKey), CredentialValidator, and full tenant setup REST API.
0028 Added Phase 4.3 system design summary.
0029 Phase 4.3.2 — Admin provisioning UI: Tenants list, TenantDetail setup hub with users, account keys, sites, site keys, agent assignment, knowledge resources, and readiness check.
0030 Improved logger: enabled/disabled DB setting, PHP fatal/exception capture, JS all-level API send, apiFetch auto-log on error.
