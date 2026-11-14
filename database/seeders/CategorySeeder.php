<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $systemCategories = [
            // Expense Categories
            ['name' => 'Makanan & Minuman', 'type' => 'expense', 'icon' => 'utensils', 'color_hex' => '#EF4444'],
            ['name' => 'Transportasi', 'type' => 'expense', 'icon' => 'car', 'color_hex' => '#F59E0B'],
            ['name' => 'Tagihan & Utilitas', 'type' => 'expense', 'icon' => 'zap', 'color_hex' => '#3B82F6'],
            ['name' => 'Belanja Kebutuhan', 'type' => 'expense', 'icon' => 'shopping-bag', 'color_hex' => '#8B5CF6'],
            ['name' => 'Hiburan & Hobi', 'type' => 'expense', 'icon' => 'film', 'color_hex' => '#EC4899'],
            ['name' => 'Kesehatan', 'type' => 'expense', 'icon' => 'heart-pulse', 'color_hex' => '#10B981'],
            ['name' => 'Pendidikan', 'type' => 'expense', 'icon' => 'book-open', 'color_hex' => '#6366F1'],
            ['name' => 'Lain-lain', 'type' => 'expense', 'icon' => 'more-horizontal', 'color_hex' => '#6B7280'],

            // Income Categories
            ['name' => 'Gaji Pokok', 'type' => 'income', 'icon' => 'briefcase', 'color_hex' => '#10B981'],
            ['name' => 'Freelance & Side Job', 'type' => 'income', 'icon' => 'laptop', 'color_hex' => '#06B6D4'],
            ['name' => 'Investasi & Dividen', 'type' => 'income', 'icon' => 'trending-up', 'color_hex' => '#8B5CF6'],
            ['name' => 'Bonus & Hadiah', 'type' => 'income', 'icon' => 'gift', 'color_hex' => '#F59E0B'],
            ['name' => 'Pendapatan Lain', 'type' => 'income', 'icon' => 'dollar-sign', 'color_hex' => '#3B82F6'],
        ];

        foreach ($systemCategories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name'], 'user_id' => null],
                $cat
            );
        }
    }
}
