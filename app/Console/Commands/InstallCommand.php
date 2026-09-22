<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

/**
 * First-run setup for a fresh deployment.
 *
 * Deliberately separate from the demo seeder: DemoSeeder creates sample
 * customers with a known password, which must never end up on a live site.
 * This creates exactly one real super admin with a password the operator
 * chooses.
 */
class InstallCommand extends Command
{
    protected $signature = 'dqr:install
        {--name= : Full name of the super admin}
        {--email= : Email the super admin signs in with}
        {--password= : Their password (prompted for when omitted)}
        {--skip-migrations : Assume the database is already migrated}';

    protected $description = 'Prepare a fresh install: run migrations and create the first super admin';

    public function handle(): int
    {
        $this->components->info('Dynamic QR — first-run setup');

        if (! $this->option('skip-migrations')) {
            $this->components->task('Running migrations', function () {
                $this->callSilently('migrate', ['--force' => true]);

                return true;
            });
        }

        if (User::where('role', UserRole::SuperAdmin->value)->exists()) {
            $this->components->warn('A super admin already exists.');

            if (! confirm('Create another one?', default: false)) {
                $this->components->info('Nothing else to do. Sign in at '.route('login'));

                return self::SUCCESS;
            }
        }

        $name = $this->option('name') ?: text(
            label: 'Full name of the super admin',
            required: true,
        );

        $email = strtolower(trim($this->option('email') ?: text(
            label: 'Email they will sign in with',
            required: true,
        )));

        $password = $this->option('password') ?: promptPassword(
            label: 'Password (at least 8 characters)',
            required: true,
        );

        try {
            $this->validate($name, $email, $password);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->newLine();
        $this->components->info('Super admin created: '.$user->email);
        $this->components->bulletList([
            'Sign in at '.route('login'),
            'Set APP_URL correctly before generating codes for print — it is baked into every QR image.',
            'Set APP_TIMEZONE so "scans today" matches your working day.',
        ]);

        return self::SUCCESS;
    }

    private function validate(string $name, string $email, string $password): void
    {
        validator(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:190', 'unique:users,email'],
                'password' => ['required', Password::min(8)],
            ],
        )->validate();
    }
}
