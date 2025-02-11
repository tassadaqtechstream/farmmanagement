<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SeedVarietySeeder::class,
            SoilTypeSeeder::class,
            CropSeeder::class,
            IrrigationSourceSeeder::class
        ]);
       /* $employee = Admin::create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('password'),

        ]);  // Get employee
        $adminRole = Role::where('name', 'admin')->first();
        $employee->roles()->attach($adminRole->id);*/
       /* $adminRole = Role::create(['name' => 'admin']);
        $editorRole = Role::create(['name' => 'editor']);

        $editArticlePermission = Permission::create(['name' => 'edit-article']);
        $viewArticlePermission = Permission::create(['name' => 'view-article']);

        $adminRole->permissions()->attach([$editArticlePermission->id, $viewArticlePermission->id]);
        $editorRole->permissions()->attach([$viewArticlePermission->id]);*/
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
