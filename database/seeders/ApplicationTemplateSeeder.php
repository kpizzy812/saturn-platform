<?php

namespace Database\Seeders;

use App\Models\ApplicationTemplate;
use Illuminate\Database\Seeder;

class ApplicationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Node.js Templates
            [
                'name' => 'Node.js Express API',
                'slug' => 'nodejs-express-api',
                'description' => 'A RESTful API server using Express.js with modern JavaScript/TypeScript patterns.',
                'category' => 'nodejs',
                'icon' => 'N',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['api', 'rest', 'backend'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '3000',
                    'install_command' => 'npm install',
                    'build_command' => 'npm run build',
                    'start_command' => 'npm start',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'NODE_ENV', 'value' => 'production', 'is_secret' => false],
                        ['key' => 'PORT', 'value' => '3000', 'is_secret' => false],
                    ],
                ],
            ],
            [
                'name' => 'Next.js Application',
                'slug' => 'nextjs-app',
                'description' => 'Full-stack React framework with server-side rendering and static generation.',
                'category' => 'nodejs',
                'icon' => 'N',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['react', 'fullstack', 'ssr'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '3000',
                    'install_command' => 'npm install',
                    'build_command' => 'npm run build',
                    'start_command' => 'npm start',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'NODE_ENV', 'value' => 'production', 'is_secret' => false],
                    ],
                ],
            ],
            [
                'name' => 'NestJS API',
                'slug' => 'nestjs-api',
                'description' => 'A progressive Node.js framework for building efficient and scalable server-side applications.',
                'category' => 'nodejs',
                'icon' => 'Ns',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['api', 'typescript', 'backend'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '3000',
                    'install_command' => 'npm install',
                    'build_command' => 'npm run build',
                    'start_command' => 'npm run start:prod',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'NODE_ENV', 'value' => 'production', 'is_secret' => false],
                    ],
                ],
            ],

            // PHP Templates
            [
                'name' => 'Laravel Application',
                'slug' => 'laravel-app',
                'description' => 'A PHP web application framework with expressive, elegant syntax.',
                'category' => 'php',
                'icon' => 'L',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['web', 'fullstack', 'api'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '8000',
                    'install_command' => 'composer install --no-dev --optimize-autoloader',
                    'build_command' => 'npm install && npm run build',
                    'start_command' => 'php artisan serve --host=0.0.0.0 --port=8000',
                    'base_directory' => '/',
                    'publish_directory' => 'public',
                    'environment_variables' => [
                        ['key' => 'APP_ENV', 'value' => 'production', 'is_secret' => false],
                        ['key' => 'APP_DEBUG', 'value' => 'false', 'is_secret' => false],
                        ['key' => 'APP_KEY', 'value' => '{{GENERATE_KEY}}', 'is_secret' => true],
                        ['key' => 'DB_CONNECTION', 'value' => 'pgsql', 'is_secret' => false],
                    ],
                ],
            ],
            [
                'name' => 'Symfony Application',
                'slug' => 'symfony-app',
                'description' => 'A set of reusable PHP components and a PHP framework for web projects.',
                'category' => 'php',
                'icon' => 'Sf',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['web', 'enterprise', 'api'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '8000',
                    'install_command' => 'composer install --no-dev --optimize-autoloader',
                    'build_command' => 'npm install && npm run build',
                    'start_command' => 'symfony server:start --port=8000',
                    'base_directory' => '/',
                    'publish_directory' => 'public',
                    'environment_variables' => [
                        ['key' => 'APP_ENV', 'value' => 'prod', 'is_secret' => false],
                        ['key' => 'APP_SECRET', 'value' => '{{GENERATE_SECRET}}', 'is_secret' => true],
                    ],
                ],
            ],

            // Python Templates
            [
                'name' => 'Django Application',
                'slug' => 'django-app',
                'description' => 'The web framework for perfectionists with deadlines.',
                'category' => 'python',
                'icon' => 'Dj',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['web', 'fullstack', 'api'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '8000',
                    'install_command' => 'pip install -r requirements.txt',
                    'build_command' => 'python manage.py collectstatic --noinput',
                    'start_command' => 'gunicorn --bind 0.0.0.0:8000 config.wsgi:application',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'DJANGO_SETTINGS_MODULE', 'value' => 'config.settings.production', 'is_secret' => false],
                        ['key' => 'SECRET_KEY', 'value' => '{{GENERATE_SECRET}}', 'is_secret' => true],
                        ['key' => 'DEBUG', 'value' => 'False', 'is_secret' => false],
                    ],
                ],
            ],
            [
                'name' => 'FastAPI Application',
                'slug' => 'fastapi-app',
                'description' => 'A modern, fast (high-performance) web framework for building APIs with Python 3.7+.',
                'category' => 'python',
                'icon' => 'Fa',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['api', 'async', 'backend'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '8000',
                    'install_command' => 'pip install -r requirements.txt',
                    'start_command' => 'uvicorn main:app --host 0.0.0.0 --port 8000',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'ENVIRONMENT', 'value' => 'production', 'is_secret' => false],
                    ],
                ],
            ],
            [
                'name' => 'Flask Application',
                'slug' => 'flask-app',
                'description' => 'A lightweight WSGI web application framework.',
                'category' => 'python',
                'icon' => 'Fl',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['web', 'api', 'microservice'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '5000',
                    'install_command' => 'pip install -r requirements.txt',
                    'start_command' => 'gunicorn --bind 0.0.0.0:5000 app:app',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'FLASK_ENV', 'value' => 'production', 'is_secret' => false],
                    ],
                ],
            ],

            // Ruby Templates
            [
                'name' => 'Ruby on Rails',
                'slug' => 'rails-app',
                'description' => 'A web application framework that includes everything needed to create database-backed web applications.',
                'category' => 'ruby',
                'icon' => 'R',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['web', 'fullstack', 'api'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '3000',
                    'install_command' => 'bundle install',
                    'build_command' => 'rails assets:precompile',
                    'start_command' => 'rails server -b 0.0.0.0 -p 3000',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'RAILS_ENV', 'value' => 'production', 'is_secret' => false],
                        ['key' => 'SECRET_KEY_BASE', 'value' => '{{GENERATE_SECRET}}', 'is_secret' => true],
                        ['key' => 'RAILS_SERVE_STATIC_FILES', 'value' => 'true', 'is_secret' => false],
                    ],
                ],
            ],

            // Go Templates
            [
                'name' => 'Go Web Server',
                'slug' => 'go-web',
                'description' => 'A simple and efficient Go web server template.',
                'category' => 'go',
                'icon' => 'Go',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['api', 'backend', 'microservice'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '8080',
                    'build_command' => 'go build -o app',
                    'start_command' => './app',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'GIN_MODE', 'value' => 'release', 'is_secret' => false],
                    ],
                ],
            ],

            // Rust Templates
            [
                'name' => 'Rust Actix Web',
                'slug' => 'rust-actix',
                'description' => 'A powerful, pragmatic, and extremely fast web framework for Rust.',
                'category' => 'rust',
                'icon' => 'Rs',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['api', 'backend', 'performance'],
                'config' => [
                    'build_pack' => 'nixpacks',
                    'ports_exposes' => '8080',
                    'build_command' => 'cargo build --release',
                    'start_command' => './target/release/app',
                    'base_directory' => '/',
                    'environment_variables' => [
                        ['key' => 'RUST_LOG', 'value' => 'info', 'is_secret' => false],
                    ],
                ],
            ],

            // Static Templates
            [
                'name' => 'Static HTML/CSS',
                'slug' => 'static-html',
                'description' => 'A simple static website with HTML, CSS, and JavaScript.',
                'category' => 'static',
                'icon' => 'H',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['static', 'web', 'landing'],
                'config' => [
                    'build_pack' => 'static',
                    'ports_exposes' => '80',
                    'base_directory' => '/',
                    'publish_directory' => '/',
                    'environment_variables' => [],
                ],
            ],
            [
                'name' => 'Vite React SPA',
                'slug' => 'vite-react',
                'description' => 'A React single-page application built with Vite.',
                'category' => 'static',
                'icon' => 'V',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['react', 'spa', 'frontend'],
                'config' => [
                    'build_pack' => 'static',
                    'ports_exposes' => '80',
                    'install_command' => 'npm install',
                    'build_command' => 'npm run build',
                    'base_directory' => '/',
                    'publish_directory' => 'dist',
                    'environment_variables' => [
                        ['key' => 'VITE_API_URL', 'value' => '{{API_URL}}', 'is_secret' => false],
                    ],
                ],
            ],

            // Docker Templates
            [
                'name' => 'Custom Dockerfile',
                'slug' => 'dockerfile-custom',
                'description' => 'Deploy any application using a custom Dockerfile.',
                'category' => 'docker',
                'icon' => 'D',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['docker', 'custom', 'flexible'],
                'config' => [
                    'build_pack' => 'dockerfile',
                    'ports_exposes' => '8080',
                    'base_directory' => '/',
                    'environment_variables' => [],
                ],
            ],
            [
                'name' => 'Docker Compose Stack',
                'slug' => 'docker-compose-stack',
                'description' => 'Deploy multi-container applications using Docker Compose.',
                'category' => 'docker',
                'icon' => 'Dc',
                'is_official' => true,
                'is_public' => true,
                'tags' => ['docker', 'multi-container', 'stack'],
                'config' => [
                    'build_pack' => 'dockercompose',
                    'ports_exposes' => '80',
                    'base_directory' => '/',
                    'environment_variables' => [],
                ],
            ],
        ];

        foreach ($templates as $templateData) {
            ApplicationTemplate::updateOrCreate(
                ['slug' => $templateData['slug']],
                $templateData
            );
        }

        $this->command->info('Created '.count($templates).' application templates.');
    }
}
