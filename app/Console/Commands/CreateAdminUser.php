<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin';
    protected $description = 'Create a secure super-admin account';

    public function handle(): int
    {
        $this->info('--- Creating New Admin Account ---');

        $firstName = $this->ask('First Name');
        $lastName  = $this->ask('Last Name');
        $email     = $this->ask('Admin Email');
        $phone     = $this->ask('Phone (Egyptian format: 01xxxxxxxxx)');
        $password  = $this->secret('Admin Password (hidden)');
        $confirm   = $this->secret('Confirm Password');

        if ($password !== $confirm) {
            $this->error('Passwords do not match!');
            return self::FAILURE;
        }

        $validator = Validator::make([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'phone'      => $phone,
            'password'   => $password,
        ], [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'phone'      => ['required', 'regex:/^01[0125][0-9]{8}$/', 'unique:users,phone'],
            'password'   => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        User::create([
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $email,
            'phone'       => $phone,
            'password'    => Hash::make($password),
            'role'        => 'admin',
            'country'     => 'Egypt',
            'governorate' => 'Cairo',
            'address'     => 'Headquarters',
        ]);

        $this->info("Admin account ({$email}) created successfully!");
        return self::SUCCESS;
    }
}