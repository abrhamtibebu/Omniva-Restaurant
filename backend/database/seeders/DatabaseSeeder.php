<?php

namespace Database\Seeders;

use App\Enums\InventoryUnit;
use App\Enums\RoleSlug;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\RecipeItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleSlug::cases() as $slug) {
            Role::query()->updateOrCreate(
                ['slug' => $slug->value],
                ['name' => $slug->label()],
            );
        }

        $restaurant = Restaurant::query()->create([
            'name' => 'Betedesta',
            'phone' => '+251 11 667 0000',
            'address' => 'Addis Ababa',
            'tax_identification_number' => '0001234567',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'active' => true,
        ]);

        $branch = Branch::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main',
            'phone' => '+251 11 667 0001',
            'address' => 'Addis Ababa',
            'tax_identification_number' => '0001234567',
            'currency' => 'ETB',
            'timezone' => 'Africa/Addis_Ababa',
            'tax_rate' => '15.00',
            'active' => true,
        ]);

        $password = Hash::make('Password123!');

        $staff = [
            ['name' => 'Admin Owner', 'email' => 'admin@omniva.test', 'slug' => RoleSlug::Admin],
            ['name' => 'Marta Manager', 'email' => 'manager@omniva.test', 'slug' => RoleSlug::Manager],
            ['name' => 'Chala Cashier', 'email' => 'cashier@omniva.test', 'slug' => RoleSlug::Cashier],
            ['name' => 'Hana Waiter', 'email' => 'waiter@omniva.test', 'slug' => RoleSlug::Waiter],
            ['name' => 'Kidus Kitchen', 'email' => 'kitchen@omniva.test', 'slug' => RoleSlug::Kitchen],
        ];

        foreach ($staff as $row) {
            User::query()->create([
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => '0911'.fake()->unique()->numerify('######'),
                'password' => $password,
                'role_id' => Role::query()->where('slug', $row['slug']->value)->value('id'),
                'branch_id' => $branch->id,
                'active' => true,
            ]);
        }

        $waiter = User::query()->where('email', 'waiter@omniva.test')->first();

        foreach (range(1, 10) as $number) {
            RestaurantTable::query()->create([
                'branch_id' => $branch->id,
                'name' => (string) $number,
                'capacity' => $number <= 4 ? 2 : ($number <= 8 ? 4 : 6),
                'status' => 'available',
                'active' => true,
                'assigned_waiter_id' => $waiter?->id,
            ]);
        }

        $categories = [];
        foreach ([
            ['የስጋ ምግቦች / Meat Dishes', 'Meat dishes served at Betedesta', 1],
            ['ቁርስ እና ፍርፍር / Breakfast & Firfir', 'Breakfast plates and firfir', 2],
            ['የፆም ምግብ / Fasting', 'Vegetarian / fasting dishes', 3],
            ['ቢራ / Beer', 'Draft and bottled beer', 4],
            ['ለስላሳ መጠጥ እና ውሃ / Soft Drinks & Water', 'Water, Ambo, and soft drinks', 5],
            ['ወይን እና አልኮል / Wine & Spirits', 'Wine, gin, cognac, and areke', 6],
        ] as [$name, $description, $sort]) {
            $categories[$name] = MenuCategory::query()->create([
                'branch_id' => $branch->id,
                'name' => $name,
                'description' => $description,
                'sort_order' => $sort,
                'active' => true,
            ]);
        }

        $modifiers = collect([
            ['Extra injera', '20.00'],
            ['Extra meat', '150.00'],
            ['Extra berbere', '15.00'],
            ['No onion', '0.00'],
            ['Extra spicy', '10.00'],
        ])->map(fn ($row) => Modifier::query()->create([
            'branch_id' => $branch->id,
            'name' => $row[0],
            'price' => $row[1],
            'active' => true,
        ]))->keyBy('name');

        $foodModifierIds = $modifiers->only(['Extra injera', 'Extra meat', 'Extra berbere', 'No onion', 'Extra spicy'])->pluck('id');

        $menu = [
            'የስጋ ምግቦች / Meat Dishes' => [
                ['ጥሬ ስጋ / Raw Meat (Tire Siga)', 'Tire siga served with awaze.', '1100.00', true, 10],
                ['የተጠበሰ ስጋ / Fried Meat (Tibs)', 'Fried tibs, house style.', '1100.00', true, 18],
                ['ጎደሎ / Special Cut', 'House specialty cut.', '1000.00', true, 18],
                ['ዱሌት / Dulet', 'Traditional dulet.', '1000.00', true, 15],
                ['ኖርማል ጥብስ / Regular Tibs', 'Regular tibs portion.', '220.00', true, 15],
            ],
            'ቁርስ እና ፍርፍር / Breakfast & Firfir' => [
                ['እንቁላል ፍርፍር / Egg Firfir', 'Scrambled egg firfir.', '120.00', true, 10],
                ['እንቁላል ሳንድዊች / Egg Sandwich', 'Egg sandwich.', '80.00', true, 8],
                ['ቋንጣ ፍርፍር / Quanta Firfir', 'Dried beef firfir.', '120.00', true, 12],
            ],
            'የፆም ምግብ / Fasting' => [
                ['በየአይነት / Beyaynetu', 'Assorted fasting platter.', '120.00', true, 12],
                ['ተጋቢኖ / Tegabino Shiro', 'Tegabino shiro.', '120.00', true, 12],
                ['በአትክልት / Vegetable Dish', 'Seasonal vegetable dish.', '120.00', true, 12],
                ['ቲማቲም ለብለብ / Tomato Salata', 'Tomato salata / firfir.', '90.00', true, 8],
            ],
            'ቢራ / Beer' => [
                ['ቢራ / Beer', 'Standard bottled beer.', '80.00', false, 2],
                ['በደሌ እና ካስትል ትልቁ / Bedele & Castle Large', 'Large Bedele or Castle.', '95.00', false, 2],
                ['አራዳ / Arada', 'Arada beer.', '90.00', false, 2],
                ['ድራፍት ሲንግል / Draft Beer Single', 'Single draft.', '50.00', false, 2],
                ['ድራፍት ጃንቦ / Draft Beer Jumbo', 'Jumbo draft.', '85.00', false, 2],
                ['ሄይነከን / Heineken', 'Heineken.', '75.00', false, 2],
            ],
            'ለስላሳ መጠጥ እና ውሃ / Soft Drinks & Water' => [
                ['1 ሊትር ውሃ / 1 Liter Water', '1 litre bottled water.', '40.00', false, 1],
                ['2 ሊትር ውሃ / 2 Liter Water', '2 litre bottled water.', '45.00', false, 1],
                ['1/2 ሊትር ውሃ / 1/2 Liter Water', '500 ml bottled water.', '30.00', false, 1],
                ['1/4 ሊትር ውሃ / 1/4 Liter Water', '250 ml bottled water.', '20.00', false, 1],
                ['ፕላስቲክ ለስላሳ / Soft Drink (Plastic)', 'Plastic-bottle soft drink.', '60.00', false, 1],
                ['አምቦ ውሃ / Ambo Water', 'Ambo mineral water.', '60.00', false, 1],
            ],
            'ወይን እና አልኮል / Wine & Spirits' => [
                ['ወይን / Wine', 'House wine.', '500.00', false, 2],
                ['አዋሽ / Awash Wine', 'Awash wine.', '550.00', false, 2],
                ['ካሚላ / Kamila Wine', 'Kamila wine.', '750.00', false, 2],
                ['አክሱሚት / Axumite Wine', 'Axumite wine.', '600.00', false, 2],
                ['ኮኛክ ደብል / Cognac (Double)', 'Cognac double.', '60.00', false, 2],
                ['ሚኒ ጅን / Mini Gin', 'Mini gin.', '225.00', false, 2],
                ['ጅን ደብል / Gin (Double)', 'Gin double.', '60.00', false, 2],
                ['የአበሻ አረቄ / Habesha Areke', 'Traditional areke.', '50.00', false, 2],
            ],
        ];

        $createdItems = [];

        foreach ($menu as $categoryName => $items) {
            foreach ($items as [$name, $description, $price, $kitchen, $prep]) {
                $item = MenuItem::query()->create([
                    'category_id' => $categories[$categoryName]->id,
                    'name' => $name,
                    'description' => $description,
                    'base_price' => $price,
                    'active' => true,
                    'available' => true,
                    'requires_kitchen' => $kitchen,
                    'preparation_time_minutes' => $prep,
                ]);
                $createdItems[$name] = $item;

                if ($kitchen) {
                    $item->modifiers()->sync($foodModifierIds);
                }
            }
        }

        $inventory = [];
        foreach ([
            ['Beef', 'INV-BEEF', InventoryUnit::Kilogram, '25', '5', '650.00'],
            ['Eggs', 'INV-EGG', InventoryUnit::Piece, '120', '24', '12.00'],
            ['Injera', 'INV-INJ', InventoryUnit::Piece, '80', '20', '8.00'],
            ['Shiro', 'INV-SHIRO', InventoryUnit::Kilogram, '8', '2', '90.00'],
            ['Vegetables', 'INV-VEG', InventoryUnit::Kilogram, '12', '3', '40.00'],
            ['Beer bottle', 'INV-BEER', InventoryUnit::Piece, '96', '24', '45.00'],
            ['Water 1L', 'INV-W1L', InventoryUnit::Piece, '60', '12', '18.00'],
            ['Soft drink bottle', 'INV-SODA', InventoryUnit::Piece, '48', '12', '28.00'],
            ['Wine bottle', 'INV-WINE', InventoryUnit::Piece, '18', '4', '280.00'],
            ['Areke', 'INV-AREKE', InventoryUnit::Liter, '6', '1', '120.00'],
        ] as [$name, $sku, $unit, $qty, $min, $cost]) {
            $inventory[$name] = InventoryItem::query()->create([
                'branch_id' => $branch->id,
                'name' => $name,
                'sku' => $sku,
                'unit' => $unit,
                'quantity_on_hand' => $qty,
                'minimum_stock' => $min,
                'average_cost' => $cost,
                'active' => true,
            ]);
        }

        $recipes = [
            'ጥሬ ስጋ / Raw Meat (Tire Siga)' => [
                ['Beef', '0.500', InventoryUnit::Kilogram],
                ['Injera', '2', InventoryUnit::Piece],
            ],
            'የተጠበሰ ስጋ / Fried Meat (Tibs)' => [
                ['Beef', '0.450', InventoryUnit::Kilogram],
                ['Injera', '2', InventoryUnit::Piece],
            ],
            'ኖርማል ጥብስ / Regular Tibs' => [
                ['Beef', '0.150', InventoryUnit::Kilogram],
                ['Injera', '1', InventoryUnit::Piece],
            ],
            'እንቁላል ፍርፍር / Egg Firfir' => [
                ['Eggs', '2', InventoryUnit::Piece],
                ['Injera', '1', InventoryUnit::Piece],
            ],
            'ተጋቢኖ / Tegabino Shiro' => [
                ['Shiro', '0.150', InventoryUnit::Kilogram],
                ['Injera', '2', InventoryUnit::Piece],
            ],
            'በአትክልት / Vegetable Dish' => [
                ['Vegetables', '0.250', InventoryUnit::Kilogram],
                ['Injera', '2', InventoryUnit::Piece],
            ],
            'ቢራ / Beer' => [
                ['Beer bottle', '1', InventoryUnit::Piece],
            ],
            '1 ሊትር ውሃ / 1 Liter Water' => [
                ['Water 1L', '1', InventoryUnit::Piece],
            ],
            'ፕላስቲክ ለስላሳ / Soft Drink (Plastic)' => [
                ['Soft drink bottle', '1', InventoryUnit::Piece],
            ],
            'አዋሽ / Awash Wine' => [
                ['Wine bottle', '1', InventoryUnit::Piece],
            ],
            'የአበሻ አረቄ / Habesha Areke' => [
                ['Areke', '0.050', InventoryUnit::Liter],
            ],
        ];

        foreach ($recipes as $itemName => $lines) {
            $item = $createdItems[$itemName] ?? null;
            if (! $item) {
                continue;
            }
            foreach ($lines as [$invName, $qty, $unit]) {
                RecipeItem::query()->create([
                    'menu_item_id' => $item->id,
                    'inventory_item_id' => $inventory[$invName]->id,
                    'quantity_required' => $qty,
                    'unit' => $unit,
                ]);
            }
        }

        Supplier::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Addis Fresh Produce',
            'contact_person' => 'Abebe Bekele',
            'phone' => '0911223344',
            'email' => 'sales@addisfresh.test',
            'address' => 'Merkato, Addis Ababa',
            'active' => true,
        ]);

        Supplier::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Highland Meats',
            'contact_person' => 'Sara Hailu',
            'phone' => '0911556677',
            'email' => 'orders@highlandmeats.test',
            'active' => true,
        ]);
    }
}
