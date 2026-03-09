<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached roles
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $guru  = Role::firstOrCreate(['name' => 'guru',  'guard_name' => 'web']);
        $siswa = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web']);

        // Create permissions
        $permissions = [
            'manage-users', 'manage-classes', 'manage-problems',
            'manage-exams', 'manage-projects', 'view-ai-review',
            'submit-code', 'run-code', 'view-dashboard',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign permissions to roles
        $admin->syncPermissions($permissions);
        $guru->syncPermissions(['manage-classes', 'manage-problems', 'manage-exams', 'manage-projects', 'view-dashboard']);
        $siswa->syncPermissions(['submit-code', 'run-code', 'view-dashboard']);

        // Create demo users
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@educode.test'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password'),
                'is_active'=> true,
            ]
        );
        $adminUser->assignRole('admin');

        $guruUser = User::firstOrCreate(
            ['email' => 'guru@educode.test'],
            [
                'name'          => 'Bu Sari (Guru)',
                'password'      => Hash::make('password'),
                'is_active'     => true,
            ]
        );
        $guruUser->assignRole('guru');

        // Create 10 sample students
        for ($i = 1; $i <= 10; $i++) {
            $student = User::firstOrCreate(
                ['email' => "siswa{$i}@educode.test"],
                [
                    'name'           => "Siswa {$i}",
                    'password'       => Hash::make('password'),
                    'student_number' => 'RPL2024' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'is_active'      => true,
                ]
            );
            $student->assignRole('siswa');
        }

        $this->command->info('✅ EduCode Studio seeded successfully!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@educode.test', 'password'],
                ['Guru',  'guru@educode.test',  'password'],
                ['Siswa', 'siswa1@educode.test', 'password'],
            ]
        );
    }
}
