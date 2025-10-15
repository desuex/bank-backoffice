<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueDemoTokensCommand extends Command
{
    protected $signature = 'bank:tokens:demo
        {--limit=10 : Number of users to process}
        {--name=demo : Token name}
        {--abilities=* : Abilities/scopes; default *}
        {--revoke : Revoke existing tokens first}
        {--json : Output a JSON array}
    ';

    protected $description = 'Issue tokens for the first N users and print them (for demo/testing)';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        $name = (string)$this->option('name') ?: 'demo';
        $abilities = $this->option('abilities') ?: ['*'];
        $revoke = (bool)$this->option('revoke');
        $asJson = (bool)$this->option('json');

        $rows = [];
        $users = User::orderBy('id')->limit($limit)->get();

        foreach ($users as $u) {
            if ($revoke) {
                $u->tokens()->delete();
            }
            $token = $u->createToken($name, (array)$abilities)->plainTextToken;

            $rows[] = [
                'id' => (string)$u->id,
                'name' => $u->name,
                'email' => $u->email,
                'token' => $token,
            ];
        }

        if ($asJson) {
            $this->line(json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['User ID', 'Name', 'Email', 'Token'], $rows);
            $this->comment("Use as:  Authorization: Bearer <token>");
        }

        return self::SUCCESS;
    }
}
