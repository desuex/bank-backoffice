<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{
    use RefreshDatabase;

    public function testBasic()
    {
        $user = User::factory()->create([
            'name'  => 'Old Name',
            'email' => 'old@example.test',
            'age'   => 25,
        ]);

        $payload = [
            'name'  => 'New Name',
            'email' => 'new@example.test',
            'age'   => 30,
        ];

        $res = $this->putJson(route('user.update', ['user' => $user->id]), $payload);

        $res->assertStatus(200)
            ->assertJson([
                'status' => true,
                'user'   => [
                    'id'    => $user->id,
                    'name'  => 'New Name',
                    'email' => 'new@example.test',
                    'age'   => 30,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'name'  => 'New Name',
            'email' => 'new@example.test',
            'age'   => 30,
        ]);
    }

    public function testInvalidData()
    {
        $user = User::factory()->create();

        $payload = [
            'name'  => '',
            'email' => 'not-an-email',
            'age'   => -1,
        ];

        $res = $this->putJson(route('user.update', ['user' => $user->id]), $payload);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'age']);
    }

    public function testEmailMustBeUniqueWhenChanged()
    {
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        $user     = User::factory()->create(['email' => 'me@example.test']);

        $res = $this->putJson(route('user.update', ['user' => $user->id]), [
            'email' => 'taken@example.test',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $res = $this->putJson(route('user.update', ['user' => $user->id]), [
            'email' => 'me@example.test',
        ]);

        $res->assertStatus(200);
    }

    public function testPartialUpdatesWork()
    {
        $user = User::factory()->create([
            'name'  => 'Alice Cooper',
            'email' => 'alice@example.test',
            'age'   => 22,
        ]);

        $res = $this->putJson(route('user.update', ['user' => $user->id]), [
            'name' => 'Bob Marley',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('user.name', 'Bob Marley')
            ->assertJsonPath('user.email', 'alice@example.test')
            ->assertJsonPath('user.age', 22);

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'Bob Marley',
        ]);
    }
}
