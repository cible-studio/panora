<?php

namespace Database\Factories;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'           => $this->faker->name(),
            'email'          => $this->faker->unique()->safeEmail(),
            'password'       => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role'           => $this->faker->randomElement(UserRole::cases())->value,
            'agent_code'     => strtoupper($this->faker->bothify('AG-####')),
            'is_active'      => true,
            'two_fa_enabled' => false,
            'last_login_at'  => null,
        ];
    }
}