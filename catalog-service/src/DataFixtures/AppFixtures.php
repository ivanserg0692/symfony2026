<?php

namespace App\DataFixtures;

use App\Entity\CatalogElements;
use App\Entity\CatalogSections;
use App\Entity\PriceType;
use App\Entity\ProductPrice;
use App\Entity\Stores;
use App\Entity\StoresElementsStocks;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * to set up, use this command:
 * docker compose run --rm catalog-cli php -d memory_limit=512M bin/console doctrine:fixtures:load --no-debug
 */
class AppFixtures extends Fixture
{
    private const PRODUCT_COUNT = 5000;
    private const PRODUCT_BATCH_SIZE = 250;
    private const CURRENCY = 'RUB';

    /**
     * Fixed catalog taxonomy for local development.
     * Only parent relations are assigned; Gedmo Tree calculates left, right and level values.
     *
     * @var array<string, array<string, mixed>>
     */
    private const CATALOG_TREE = [
        'Catalog' => [
            'Electronics' => [
                'Computers' => [
                    'Laptops' => [
                        'Gaming Laptops' => [],
                        'Business Laptops' => [],
                        'Ultrabooks' => [],
                    ],
                    'Desktop PCs' => [
                        'Gaming PCs' => [],
                        'Office PCs' => [],
                        'Workstations' => [],
                    ],
                    'Components' => [
                        'CPUs' => [],
                        'Motherboards' => [],
                        'RAM' => [],
                        'SSD' => [],
                        'HDD' => [],
                        'GPUs' => [],
                    ],
                ],
                'Phones' => [
                    'Android' => [
                        'Samsung' => [],
                        'Xiaomi' => [],
                        'Google Pixel' => [],
                    ],
                    'iPhone' => [],
                    'Accessories' => [
                        'Cases' => [],
                        'Chargers' => [],
                        'Screen Protectors' => [],
                    ],
                ],
                'Tablets' => [
                    'Android Tablets' => [],
                    'iPad' => [],
                    'Accessories' => [],
                ],
                'TV & Audio' => [
                    'TVs' => [],
                    'Soundbars' => [],
                    'Headphones' => [],
                    'Speakers' => [],
                ],
            ],
            'Home Appliances' => [
                'Kitchen' => [
                    'Coffee Machines' => [],
                    'Microwaves' => [],
                    'Ovens' => [],
                    'Dishwashers' => [],
                ],
                'Laundry' => [
                    'Washing Machines' => [],
                    'Dryers' => [],
                    'Ironing Systems' => [],
                ],
                'Cleaning' => [
                    'Vacuum Cleaners' => [],
                    'Robot Vacuums' => [],
                    'Steam Cleaners' => [],
                ],
            ],
            'Home & Garden' => [
                'Furniture' => [
                    'Bedroom' => [],
                    'Living Room' => [],
                    'Kitchen' => [],
                    'Office' => [],
                ],
                'Lighting' => [],
                'Garden' => [
                    'Lawn Mowers' => [],
                    'Irrigation' => [],
                    'Outdoor Furniture' => [],
                ],
                'Tools' => [
                    'Power Tools' => [],
                    'Hand Tools' => [],
                    'Measuring Tools' => [],
                ],
            ],
            'Clothing' => [
                'Men' => [
                    'Jackets' => [],
                    'Jeans' => [],
                    'Shoes' => [],
                    'Accessories' => [],
                ],
                'Women' => [
                    'Dresses' => [],
                    'Shoes' => [],
                    'Bags' => [],
                    'Accessories' => [],
                ],
                'Kids' => [
                    'Boys' => [],
                    'Girls' => [],
                    'Baby' => [],
                ],
            ],
            'Sports & Outdoor' => [
                'Fitness' => [
                    'Cardio' => [],
                    'Strength' => [],
                    'Accessories' => [],
                ],
                'Cycling' => [
                    'Mountain Bikes' => [],
                    'Road Bikes' => [],
                    'Accessories' => [],
                ],
                'Hiking' => [
                    'Tents' => [],
                    'Sleeping Bags' => [],
                    'Backpacks' => [],
                ],
                'Winter Sports' => [
                    'Skis' => [],
                    'Snowboards' => [],
                    'Clothing' => [],
                ],
            ],
        ],
    ];

    /**
     * Fixed store network used for product stock generation.
     *
     * @var array<int, array{name: string, slug: string, description: string}>
     */
    private const STORES = [
        [
            'name' => 'Main Warehouse',
            'slug' => 'main-warehouse',
            'description' => 'Central fulfillment warehouse for online orders and regional replenishment.',
        ],
        [
            'name' => 'Moscow Store',
            'slug' => 'moscow-store',
            'description' => 'Flagship retail store with pickup and same-day delivery in Moscow.',
        ],
        [
            'name' => 'Saint Petersburg Store',
            'slug' => 'saint-petersburg-store',
            'description' => 'Retail and pickup location serving Saint Petersburg and nearby areas.',
        ],
        [
            'name' => 'Kazan Store',
            'slug' => 'kazan-store',
            'description' => 'Regional store and pickup point for Volga area customers.',
        ],
        [
            'name' => 'Novosibirsk Store',
            'slug' => 'novosibirsk-store',
            'description' => 'Siberian regional store with warehouse-backed product availability.',
        ],
    ];

    /**
     * Fixed product price types.
     * ProductPrice rows later reference these IDs and keep one row per product/type pair.
     *
     * @var array<string, array{name: string, description: string, sort: int}>
     */
    private const PRICE_TYPES = [
        'BASE' => [
            'name' => 'Base price',
            'description' => 'Default retail price for the product.',
            'sort' => 100,
        ],
        'SALE' => [
            'name' => 'Sale price',
            'description' => 'Limited promotional price lower than the base price.',
            'sort' => 200,
        ],
        'WHOLESALE' => [
            'name' => 'Wholesale price',
            'description' => 'Bulk purchase price for wholesale customers.',
            'sort' => 300,
        ],
        'VIP' => [
            'name' => 'VIP price',
            'description' => 'Preferred customer price.',
            'sort' => 400,
        ],
    ];

    /**
     * Brand names are intentionally broad so every catalog branch can produce plausible products.
     *
     * @var array<int, string>
     */
    private const PRODUCT_BRANDS = [
        'Acer',
        'Apple',
        'Asus',
        'Bosch',
        'Canon',
        'DeLonghi',
        'Dyson',
        'Electrolux',
        'Garmin',
        'HP',
        'Huawei',
        'Indesit',
        'Lenovo',
        'LG',
        'Nike',
        'Philips',
        'Samsung',
        'Sony',
        'Tefal',
        'Xiaomi',
    ];

    /**
     * Product lines are combined with brand, category-specific type and model number.
     *
     * @var array<int, string>
     */
    private const PRODUCT_LINES = [
        'Air',
        'Apex',
        'Classic',
        'Comfort',
        'Edge',
        'Elite',
        'Flex',
        'Max',
        'Nova',
        'Prime',
        'Pro',
        'Smart',
        'Studio',
        'Ultra',
        'Vector',
    ];

    public function load(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('Expected %s.', EntityManagerInterface::class));
        }

        $faker = Factory::create('en_US');
        $faker->seed(20260705);

        // Leaf section IDs are kept after section creation so products are never attached to intermediate categories.
        $leafSections = [];
        $this->loadCatalogSections($manager, self::CATALOG_TREE, null, [], $leafSections);
        $storeIds = $this->loadStores($manager);
        $priceTypeIds = $this->loadPriceTypes($manager);

        // Clear the identity map before the large product batch to keep memory usage predictable.
        $manager->flush();
        $manager->clear();

        $this->loadProducts($manager, $faker, $leafSections, $storeIds, $priceTypeIds);
    }

    /**
     * @param array<string, array<string, mixed>> $tree
     * @param array<int, string>                  $path
     * @param array<int, string>                  $leafSections
     */
    private function loadCatalogSections(
        ObjectManager $manager,
        array $tree,
        ?CatalogSections $parent,
        array $path,
        array &$leafSections,
    ): void {
        $sort = 100;

        foreach ($tree as $name => $children) {
            $sectionPath = [...$path, $name];
            $section = new CatalogSections();
            $section
                ->setName($name)
                ->setSlug($this->slugify(implode(' ', $sectionPath)))
                ->setActive(true)
                ->setDescription(sprintf('Products and offers in the %s catalog section.', $name))
                ->setPictureId(sprintf('catalog/sections/%s.webp', $this->slugify(implode('-', $sectionPath))))
                ->setSort($sort)
                ->setParent($parent);

            $manager->persist($section);

            if ($children === []) {
                // Flush here only to obtain the generated section ID for later product assignment.
                $manager->flush();
                $leafSections[(int) $section->getId()] = $name;
            } else {
                /** @var array<string, array<string, mixed>> $children */
                $this->loadCatalogSections($manager, $children, $section, $sectionPath, $leafSections);
            }

            $sort += 100;
        }
    }

    /**
     * @return array<int, int>
     */
    private function loadStores(ObjectManager $manager): array
    {
        $storeIds = [];

        foreach (self::STORES as $storeData) {
            $store = new Stores();
            $store
                ->setName($storeData['name'])
                ->setSlug($storeData['slug'])
                ->setActive(true)
                ->setDescription($storeData['description']);

            $manager->persist($store);
            $manager->flush();

            $storeIds[] = (int) $store->getId();
        }

        return $storeIds;
    }

    /**
     * @return array<string, int>
     */
    private function loadPriceTypes(ObjectManager $manager): array
    {
        $priceTypeIds = [];
        $now = new \DateTimeImmutable();

        foreach (self::PRICE_TYPES as $code => $priceTypeData) {
            $priceType = new PriceType();
            $priceType
                ->setCode($code)
                ->setName($priceTypeData['name'])
                ->setDescription($priceTypeData['description'])
                ->setActive(true)
                ->setSort($priceTypeData['sort'])
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $manager->persist($priceType);
            $manager->flush();

            $priceTypeIds[$code] = (int) $priceType->getId();
        }

        return $priceTypeIds;
    }

    /**
     * @param array<int, string> $leafSections
     * @param array<int, int>    $storeIds
     * @param array<string, int> $priceTypeIds
     */
    private function loadProducts(
        EntityManagerInterface $manager,
        Generator $faker,
        array $leafSections,
        array $storeIds,
        array $priceTypeIds,
    ): void {
        for ($i = 1; $i <= self::PRODUCT_COUNT; ++$i) {
            $createdAt = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-18 months', '-1 day'));
            // Each product belongs to one to three leaf categories, never to a parent category.
            $sectionIds = $this->pickRandomIds($faker, array_keys($leafSections), 1, 3);
            $productName = $this->createProductName($faker, $leafSections[$sectionIds[0]]);
            $pictureUuid = $faker->uuid();

            $product = new CatalogElements();
            $product
                ->setName($productName)
                ->setCreatedAt($createdAt)
                ->setActive($faker->numberBetween(1, 100) <= 90)
                ->setCreatedBy($faker->numberBetween(1, 20))
                ->setDescription($faker->paragraphs($faker->numberBetween(2, 4), true))
                ->setSlug(sprintf('%s-%05d', $this->slugify($productName), $i))
                ->setPictureId(sprintf('products/%s/main.webp', $pictureUuid))
                ->setSort($faker->numberBetween(10, 500));

            foreach ($sectionIds as $sectionId) {
                $product->addSection($manager->getReference(CatalogSections::class, $sectionId));
            }

            $manager->persist($product);

            $basePrice = $faker->numberBetween(50000, 50000000);
            $this->persistProductPrice($manager, $product, $priceTypeIds['BASE'], $basePrice, $createdAt);

            // Optional price rows respect the product/type unique constraint by being created at most once.
            if ($faker->numberBetween(1, 100) <= 25) {
                $this->persistProductPrice(
                    $manager,
                    $product,
                    $priceTypeIds['SALE'],
                    $this->calculateLowerPrice($faker, $basePrice, 70, 95),
                    $createdAt,
                );
            }

            if ($faker->numberBetween(1, 100) <= 40) {
                $this->persistProductPrice(
                    $manager,
                    $product,
                    $priceTypeIds['WHOLESALE'],
                    $this->calculateLowerPrice($faker, $basePrice, 60, 90),
                    $createdAt,
                );
            }

            if ($faker->numberBetween(1, 100) <= 15) {
                $this->persistProductPrice(
                    $manager,
                    $product,
                    $priceTypeIds['VIP'],
                    $this->calculateVipPrice($faker, $basePrice),
                    $createdAt,
                );
            }

            // Stock rows use unique store IDs, which keeps the composite store/product key valid.
            foreach ($this->pickRandomIds($faker, $storeIds, 1, 5) as $storeId) {
                $stock = new StoresElementsStocks();
                $stock
                    ->setElement($product)
                    ->setStore($manager->getReference(Stores::class, $storeId))
                    ->setStock($faker->numberBetween(0, 200));

                $manager->persist($stock);
            }

            if ($i % self::PRODUCT_BATCH_SIZE === 0) {
                // Large fixture loads are flushed in batches to avoid keeping all products, prices and stock rows in memory.
                $manager->flush();
                $manager->clear();
            }
        }

        $manager->flush();
        $manager->clear();
    }

    private function persistProductPrice(
        EntityManagerInterface $manager,
        CatalogElements $product,
        int $priceTypeId,
        int $price,
        \DateTimeImmutable $createdAt,
    ): void {
        $productPrice = new ProductPrice();
        $productPrice
            ->setProduct($product)
            ->setPriceType($manager->getReference(PriceType::class, $priceTypeId))
            ->setPrice($price)
            ->setCurrency(self::CURRENCY)
            ->setActive(true)
            ->setValidFrom($createdAt)
            ->setValidTo(null)
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($createdAt);

        $manager->persist($productPrice);
    }

    private function createProductName(Generator $faker, string $categoryName): string
    {
        return sprintf(
            '%s %s %s %d',
            $faker->randomElement(self::PRODUCT_BRANDS),
            $faker->randomElement(self::PRODUCT_LINES),
            $this->createProductType($faker, $categoryName),
            $faker->numberBetween(100, 9999),
        );
    }

    private function createProductType(Generator $faker, string $categoryName): string
    {
        // Leaf-category specific nouns make generated product names look like a real e-commerce catalog.
        $typesByCategory = [
            'Gaming Laptops' => ['Gaming Laptop', 'RTX Laptop', 'High Refresh Laptop'],
            'Business Laptops' => ['Business Laptop', 'Docking Laptop', 'Office Laptop'],
            'Ultrabooks' => ['Ultrabook', 'Thin Laptop', 'Portable Laptop'],
            'Gaming PCs' => ['Gaming PC', 'RGB Desktop', 'Performance Tower'],
            'Office PCs' => ['Office PC', 'Compact Desktop', 'Business Desktop'],
            'Workstations' => ['Workstation', 'Creator Workstation', 'Rendering Station'],
            'CPUs' => ['CPU', 'Desktop Processor', 'Multicore Processor'],
            'Motherboards' => ['Motherboard', 'Gaming Motherboard', 'ATX Motherboard'],
            'RAM' => ['Memory Kit', 'DDR5 RAM', 'Laptop Memory'],
            'SSD' => ['NVMe SSD', 'SATA SSD', 'Portable SSD'],
            'HDD' => ['Hard Drive', 'NAS HDD', 'Desktop HDD'],
            'GPUs' => ['Graphics Card', 'Gaming GPU', 'Creator GPU'],
            'Samsung' => ['Samsung Smartphone', 'Galaxy Phone', 'Android Phone'],
            'Xiaomi' => ['Xiaomi Smartphone', 'Redmi Phone', 'Android Phone'],
            'Google Pixel' => ['Pixel Smartphone', 'Google Phone', 'Android Camera Phone'],
            'iPhone' => ['iPhone', 'iOS Smartphone', 'Apple Smartphone'],
            'Cases' => ['Protective Case', 'Silicone Case', 'Folio Case'],
            'Chargers' => ['Fast Charger', 'USB-C Charger', 'Wireless Charger'],
            'Screen Protectors' => ['Screen Protector', 'Tempered Glass', 'Privacy Glass'],
            'Android Tablets' => ['Android Tablet', 'LTE Tablet', 'Kids Tablet'],
            'iPad' => ['iPad', 'iPad Case Bundle', 'Apple Tablet'],
            'TVs' => ['4K TV', 'OLED TV', 'Smart TV'],
            'Soundbars' => ['Soundbar', 'Dolby Soundbar', 'TV Speaker Bar'],
            'Headphones' => ['Wireless Headphones', 'Noise Cancelling Headphones', 'Gaming Headset'],
            'Speakers' => ['Bluetooth Speaker', 'Bookshelf Speaker', 'Portable Speaker'],
            'Coffee Machines' => ['Coffee Machine', 'Espresso Maker', 'Bean-to-Cup Machine'],
            'Microwaves' => ['Microwave Oven', 'Grill Microwave', 'Compact Microwave'],
            'Ovens' => ['Built-in Oven', 'Electric Oven', 'Convection Oven'],
            'Dishwashers' => ['Dishwasher', 'Built-in Dishwasher', 'Compact Dishwasher'],
            'Washing Machines' => ['Washing Machine', 'Front Load Washer', 'Slim Washer'],
            'Dryers' => ['Dryer', 'Heat Pump Dryer', 'Laundry Dryer'],
            'Ironing Systems' => ['Ironing System', 'Steam Station', 'Garment Steamer'],
            'Vacuum Cleaners' => ['Vacuum Cleaner', 'Cordless Vacuum', 'Canister Vacuum'],
            'Robot Vacuums' => ['Robot Vacuum', 'Vacuum Mop Robot', 'Self-emptying Robot'],
            'Steam Cleaners' => ['Steam Cleaner', 'Steam Mop', 'Handheld Steamer'],
            'Bedroom' => ['Bed Frame', 'Wardrobe', 'Bedside Table'],
            'Living Room' => ['Sofa', 'TV Stand', 'Coffee Table'],
            'Kitchen' => ['Kitchen Cabinet', 'Dining Set', 'Bar Stool'],
            'Office' => ['Office Desk', 'Ergonomic Chair', 'Filing Cabinet'],
            'Lighting' => ['LED Lamp', 'Ceiling Light', 'Desk Lamp'],
            'Lawn Mowers' => ['Lawn Mower', 'Cordless Mower', 'Robotic Mower'],
            'Irrigation' => ['Irrigation Kit', 'Garden Sprinkler', 'Watering Timer'],
            'Outdoor Furniture' => ['Patio Chair', 'Garden Table', 'Outdoor Sofa'],
            'Power Tools' => ['Cordless Drill', 'Angle Grinder', 'Impact Driver'],
            'Hand Tools' => ['Tool Set', 'Screwdriver Kit', 'Wrench Set'],
            'Measuring Tools' => ['Laser Measure', 'Digital Level', 'Tape Measure'],
            'Jackets' => ['Jacket', 'Puffer Jacket', 'Softshell Jacket'],
            'Jeans' => ['Jeans', 'Slim Jeans', 'Regular Fit Jeans'],
            'Shoes' => ['Shoes', 'Sneakers', 'Running Shoes'],
            'Dresses' => ['Dress', 'Evening Dress', 'Casual Dress'],
            'Bags' => ['Bag', 'Crossbody Bag', 'Backpack'],
            'Boys' => ['Boys Hoodie', 'Boys Sneakers', 'Boys Jacket'],
            'Girls' => ['Girls Dress', 'Girls Sneakers', 'Girls Jacket'],
            'Baby' => ['Baby Bodysuit', 'Baby Stroller', 'Baby Blanket'],
            'Cardio' => ['Treadmill', 'Exercise Bike', 'Elliptical Trainer'],
            'Strength' => ['Dumbbell Set', 'Weight Bench', 'Kettlebell'],
            'Mountain Bikes' => ['Mountain Bike', 'Trail Bike', 'Hardtail Bike'],
            'Road Bikes' => ['Road Bike', 'Gravel Bike', 'Carbon Bike'],
            'Tents' => ['Camping Tent', 'Family Tent', 'Backpacking Tent'],
            'Sleeping Bags' => ['Sleeping Bag', 'Winter Sleeping Bag', 'Lightweight Sleeping Bag'],
            'Backpacks' => ['Hiking Backpack', 'Travel Backpack', 'Hydration Pack'],
            'Skis' => ['Alpine Skis', 'Freeride Skis', 'Ski Set'],
            'Snowboards' => ['Snowboard', 'Freestyle Snowboard', 'All-mountain Snowboard'],
            'Clothing' => ['Thermal Jacket', 'Ski Pants', 'Base Layer'],
            'Accessories' => ['Accessory Kit', 'Travel Accessory', 'Daily Accessory'],
        ];

        return $faker->randomElement($typesByCategory[$categoryName] ?? [$categoryName]);
    }

    /**
     * @param array<int, int> $ids
     *
     * @return array<int, int>
     */
    private function pickRandomIds(Generator $faker, array $ids, int $min, int $max): array
    {
        $count = $faker->numberBetween($min, min($max, \count($ids)));
        $keys = (array) array_rand($ids, $count);
        $selectedIds = [];

        foreach ($keys as $key) {
            $selectedIds[] = $ids[$key];
        }

        return $selectedIds;
    }

    private function calculateLowerPrice(Generator $faker, int $basePrice, int $minPercent, int $maxPercent): int
    {
        return max(1, min($basePrice - 1, intdiv($basePrice * $faker->numberBetween($minPercent, $maxPercent), 100)));
    }

    private function calculateVipPrice(Generator $faker, int $basePrice): int
    {
        return max(1, intdiv($basePrice * $faker->numberBetween(80, 100), 100));
    }

    private function slugify(string $value): string
    {
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'item';
    }
}
