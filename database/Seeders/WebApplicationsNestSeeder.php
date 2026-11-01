<?php

namespace Database\Seeders;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Illuminate\Database\Seeder;
use Pterodactyl\Models\EggVariable;

class WebApplicationsNestSeeder extends Seeder
{
    /**
     * Seed the Web Applications Nest and Multi-Language Runtimes.
     */
    public function run(): void
    {
        $nest = Nest::query()->firstOrCreate(
            ['name' => 'Web Applications'],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'author' => 'system@n1x.panel',
                'description' => 'Multi-Language Web Applications (Node.js, Python, PHP, Static) running natively without Docker.',
            ]
        );

        $this->createNodeJsEgg($nest);
        $this->createPythonEgg($nest);
        $this->createPhpEgg($nest);
        $this->createStaticEgg($nest);
    }

    protected function createNodeJsEgg(Nest $nest): void
    {
        $egg = Egg::query()->firstOrCreate(
            ['nest_id' => $nest->id, 'name' => 'Node.js Application'],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'author' => 'system@n1x.panel',
                'description' => 'High-performance Node.js application hosting (Express, Nest, Next, Fastify, vanilla).',
                'docker_images' => ['ghcr.io/pterodactyl/yolks:node_20' => 'ghcr.io/pterodactyl/yolks:node_20'],
                'startup' => 'node {{STARTUP_FILE}}',
                'config_stop' => '^C',
                'config_startup' => json_encode(['done' => 'listening on', 'userInteraction' => []]),
                'config_logs' => json_encode([]),
                'config_files' => json_encode([]),
                'config_from' => null,
                'script_entry' => 'bash',
                'script_container' => 'ghcr.io/pterodactyl/yolks:node_20',
                'script_install' => 'npm install --production',
            ]
        );

        EggVariable::query()->firstOrCreate(
            ['egg_id' => $egg->id, 'env_variable' => 'STARTUP_FILE'],
            [
                'name' => 'Startup File',
                'description' => 'Entry point JavaScript file (e.g. app.js, server.js, index.js)',
                'default_value' => 'index.js',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:191',
            ]
        );

        EggVariable::query()->firstOrCreate(
            ['egg_id' => $egg->id, 'env_variable' => 'NODE_ENV'],
            [
                'name' => 'Node Environment',
                'description' => 'Application environment mode',
                'default_value' => 'production',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|in:production,development,test',
            ]
        );
    }

    protected function createPythonEgg(Nest $nest): void
    {
        $egg = Egg::query()->firstOrCreate(
            ['nest_id' => $nest->id, 'name' => 'Python Application'],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'author' => 'system@n1x.panel',
                'description' => 'Python web application hosting with virtual environment (Flask, FastAPI, Django, etc.).',
                'docker_images' => ['ghcr.io/pterodactyl/yolks:python_3.11' => 'ghcr.io/pterodactyl/yolks:python_3.11'],
                'startup' => 'python3 {{STARTUP_FILE}}',
                'config_stop' => '^C',
                'config_startup' => json_encode(['done' => 'Application startup complete', 'userInteraction' => []]),
                'config_logs' => json_encode([]),
                'config_files' => json_encode([]),
                'config_from' => null,
                'script_entry' => 'bash',
                'script_container' => 'ghcr.io/pterodactyl/yolks:python_3.11',
                'script_install' => 'python3 -m venv venv && ./venv/bin/pip install -r requirements.txt',
            ]
        );

        EggVariable::query()->firstOrCreate(
            ['egg_id' => $egg->id, 'env_variable' => 'STARTUP_FILE'],
            [
                'name' => 'Startup File',
                'description' => 'Entry point Python file (e.g. app.py, main.py)',
                'default_value' => 'app.py',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:191',
            ]
        );
    }

    protected function createPhpEgg(Nest $nest): void
    {
        $egg = Egg::query()->firstOrCreate(
            ['nest_id' => $nest->id, 'name' => 'PHP Application'],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'author' => 'system@n1x.panel',
                'description' => 'PHP web application hosting (Laravel, WordPress, native PHP).',
                'docker_images' => ['ghcr.io/pterodactyl/yolks:php_8.2' => 'ghcr.io/pterodactyl/yolks:php_8.2'],
                'startup' => 'php -S 0.0.0.0:{{SERVER_PORT}} -t {{DOC_ROOT}} {{STARTUP_FILE}}',
                'config_stop' => '^C',
                'config_startup' => json_encode(['done' => 'Development Server', 'userInteraction' => []]),
                'config_logs' => json_encode([]),
                'config_files' => json_encode([]),
                'config_from' => null,
                'script_entry' => 'bash',
                'script_container' => 'ghcr.io/pterodactyl/yolks:php_8.2',
                'script_install' => 'composer install --no-dev --optimize-autoloader',
            ]
        );

        EggVariable::query()->firstOrCreate(
            ['egg_id' => $egg->id, 'env_variable' => 'DOC_ROOT'],
            [
                'name' => 'Document Root',
                'description' => 'Document root directory (e.g. public or .)',
                'default_value' => '.',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:191',
            ]
        );

        EggVariable::query()->firstOrCreate(
            ['egg_id' => $egg->id, 'env_variable' => 'STARTUP_FILE'],
            [
                'name' => 'Startup File',
                'description' => 'Startup script (e.g. index.php)',
                'default_value' => 'index.php',
                'user_viewable' => true,
                'user_editable' => true,
                'rules' => 'required|string|max:191',
            ]
        );
    }

    protected function createStaticEgg(Nest $nest): void
    {
        $egg = Egg::query()->firstOrCreate(
            ['nest_id' => $nest->id, 'name' => 'Static Website'],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'author' => 'system@n1x.panel',
                'description' => 'HTML, CSS, and client-side JavaScript static website hosting.',
                'docker_images' => ['ghcr.io/pterodactyl/yolks:node_20' => 'ghcr.io/pterodactyl/yolks:node_20'],
                'startup' => 'npx serve -l {{SERVER_PORT}} -s .',
                'config_stop' => '^C',
                'config_startup' => json_encode(['done' => 'Accepting connections', 'userInteraction' => []]),
                'config_logs' => json_encode([]),
                'config_files' => json_encode([]),
                'config_from' => null,
                'script_entry' => 'bash',
                'script_container' => 'ghcr.io/pterodactyl/yolks:node_20',
                'script_install' => 'echo "Static files ready"',
            ]
        );
    }
}
