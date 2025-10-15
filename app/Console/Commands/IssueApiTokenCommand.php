<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;


class IssueApiTokenCommand extends Command
{
    protected $signature = 'bank:token
        {identifier : User ID or email}
        {--name=cli : Token name (for display/auditing)}
        {--abilities=* : Abilities/scopes, e.g. --abilities=top-up --abilities=transfer}
        {--revoke : Revoke user\'s existing tokens before issuing}
        {--json : Output JSON only (token, user)}
    ';

    protected $description = 'Issue a Sanctum personal access token for a user (by id or email)';

    public function handle(): int
    {
        $idOrEmail = (string)$this->argument('identifier');
        /** @var User|null $user */
        $user = filter_var($idOrEmail, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $idOrEmail)->first()
            : User::find($idOrEmail);

        if (!$user) {
            $this->error("User not found: {$idOrEmail}");
            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $user->tokens()->delete();
        }

        $name = (string)$this->option('name') ?: 'cli';
        $abilities = Arr::wrap($this->option('abilities'));
        if (empty($abilities)) {
            $abilities = ['*'];
        }

        $plainTextToken = $user->createToken($name, $abilities)->plainTextToken;

        if ($this->option('json')) {
            $this->line(json_encode([
                'token' => $plainTextToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info('Issued token:');
        $this->line($plainTextToken);
        $this->newLine();

        $this->comment('Example curl (transfer):');
        $this->line(sprintf(
            "curl -X POST %s/api/transfers \\\n -H 'Authorization: Bearer %s' \\\n -H 'Content-Type: application/json' \\\n -d '{\"from_account_id\":\"<uuid>\",\"to_account_id\":\"<uuid>\",\"amount\":1000,\"currency_code\":\"EUR\"}'",
            config('app.url') ?? 'http://localhost',
            $plainTextToken
        ));
        $this->newLine();

        $this->table(
            ['User ID', 'Name', 'Email', 'Token name', 'Abilities'],
            [[(string)$user->id, $user->name, $user->email, $name, implode(',', $abilities)]]
        );

        return self::SUCCESS;
    }
}
