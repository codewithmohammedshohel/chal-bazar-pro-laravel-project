<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * CLI-only provisioning of the first owner (superadmin) account.
 *
 * There is deliberately no default identity or password, and no web route
 * performs this promotion. The password is entered interactively and is never
 * echoed or written to logs/history.
 */
class ProvisionOwner extends Command
{
    protected $signature = 'app:provision-owner
        {email : Email address of the owner account}
        {--name= : Display name for the owner account}
        {--force : Promote/reset an existing account instead of aborting}
        {--allow-production : Permit execution in the production environment}';

    protected $description = 'Provision (or promote) a superadmin owner account from the CLI';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        if (app()->environment('production')) {
            if (! $this->option('allow-production')) {
                $this->error('Refusing to provision an owner in production without --allow-production.');

                return self::FAILURE;
            }

            if (! $this->confirm('You are about to provision an owner against a production database. Continue?', false)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && ! $this->option('force')) {
            $this->error('An account with this email already exists. Re-run with --force to promote it.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Owner name')));

        if ($name === '') {
            $this->error('An owner name is required.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min 12 chars, upper/lowercase, number, symbol)');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password !== $confirmation) {
            $this->error('The password confirmation does not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $existing ?? new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->role = Permissions::ROLE_SUPERADMIN;
        $user->status = Permissions::STATUS_ACTIVE;
        $user->email_verified_at ??= now();
        $user->save();

        $this->info(sprintf(
            'Owner account provisioned: %s (role: %s, status: %s).',
            $user->email,
            $user->role,
            $user->status,
        ));

        return self::SUCCESS;
    }
}
