<?php

namespace App\Support\Demo;

use App\Enums\HalalStatus;
use App\Enums\ListingCategory;
use App\Enums\ListingType;

/**
 * DEMO DATA ONLY. What the five demo shops "sell": the catalogue the demo
 * deals are picked from (App\Support\Demo\DemoDeals). The shops are real
 * places near UiTM Kedah, but they have not joined FoodLoop: every item,
 * price, allergen list and halal status here is made up.
 *
 * To add an item, add an entry to items(). Prices are in sen (RM8 = 800).
 *
 *   shop     which demo shop sells it (the part of its login before the @)
 *   photo    file name of its picture, without the ending (see DemoPhotos)
 *   picture  the general drawing used while the item has no picture of its
 *            own ("kuih", "rice-meal"…). Leave out when `photo` is drawn.
 */
class DemoMenu
{
    /** The login (before @foodloop.test) of each demo shop, and what kind of place it is. */
    public const SHOPS = [
        'bondamawar' => 'Bonda Mawar Cake House',
        'merbokroasted' => 'Merbok Roasted',
        'kopisaigon' => 'Kopi Saigon Merbok',
        'kuihtupah' => 'Kuih Tradisional Tupah',
        'sebunga' => 'Se.Bunga Kafe UiTM',
    ];

    /** Always posted, always the same: docs/DEMO_SCRIPT.md is written around these two. */
    public const ANCHORS = ['bundle', 'sandwich'];

    /** The six deals on the approved mockups (DEMO_MENU=mockup posts exactly these). */
    public const MOCKUP = ['puffs', 'latte', 'bundle', 'nasiayam', 'sandwich', 'croissants'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function items(): array
    {
        $baked = ['egg', 'milk', 'wheat'];

        return [
            // Bonda Mawar Cake House (cake shop, the one "halal certified" demo shop)
            'bundle' => self::item('bondamawar', 'Bakery Surprise Bundle', 2000, 800, ListingCategory::Bakery, 'bakery-bundle', $baked,
                '4–6 of today’s unsold breads, buns and pastries. You’ll find out what’s inside at pickup.', type: ListingType::Bundle),
            'croissants' => self::item('bondamawar', 'Butter croissants × 3', 1200, 600, ListingCategory::Bakery, 'croissants', $baked,
                'Three butter croissants baked this morning.', type: ListingType::Flash),
            'cheesetarts' => self::item('bondamawar', 'Cheese tarts × 4', 1600, 800, ListingCategory::Bakery, 'cheese-tarts', $baked,
                'Four baked cheese tarts from today’s tray.', picture: 'pastry'),
            'choccake' => self::item('bondamawar', 'Chocolate cake slice × 2', 1800, 900, ListingCategory::Bakery, 'chocolate-cake', $baked,
                'Two slices of chocolate fudge cake, cut today.', picture: 'cake-slice'),
            'pandancake' => self::item('bondamawar', 'Pandan layer cake slice × 2', 1600, 700, ListingCategory::Bakery, 'pandan-cake', $baked,
                'Two slices of pandan layer cake with coconut cream.', picture: 'cake-slice'),
            'sausagebuns' => self::item('bondamawar', 'Sausage buns × 4', 1200, 500, ListingCategory::Bakery, 'sausage-buns', $baked,
                'Four soft chicken sausage buns baked this morning.', picture: 'pastry'),
            'brownies' => self::item('bondamawar', 'Fudge brownies × 3', 1500, 600, ListingCategory::Bakery, 'brownies', [...$baked, 'nuts'],
                'Three walnut fudge brownies from today’s batch.', picture: 'cake-slice'),

            // Kuih Tradisional Tupah (traditional kuih maker)
            'puffs' => self::item('kuihtupah', 'Curry puffs × 4', 600, 300, ListingCategory::Kuih, 'curry-puffs', ['wheat'],
                'Four potato curry puffs from this afternoon’s batch.'),
            'kuihlapis' => self::item('kuihtupah', 'Kuih lapis × 6', 600, 300, ListingCategory::Kuih, 'kuih-lapis', [],
                'Six pieces of steamed layer kuih, made this morning.', picture: 'kuih'),
            'ondeonde' => self::item('kuihtupah', 'Onde-onde × 10', 500, 250, ListingCategory::Kuih, 'onde-onde', [],
                'Ten pandan onde-onde with gula Melaka, rolled in fresh coconut.', picture: 'kuih'),
            'serimuka' => self::item('kuihtupah', 'Seri muka × 6', 700, 300, ListingCategory::Kuih, 'seri-muka', ['egg'],
                'Six pieces of seri muka: glutinous rice with pandan custard on top.', picture: 'kuih'),
            'ketayap' => self::item('kuihtupah', 'Kuih ketayap × 5', 600, 250, ListingCategory::Kuih, 'kuih-ketayap', ['egg', 'wheat'],
                'Five pandan crepes rolled around sweet coconut.', picture: 'kuih'),
            'kuihbundle' => self::item('kuihtupah', 'Kuih Surprise Bundle', 1200, 500, ListingCategory::Kuih, 'kuih-bundle', ['egg', 'wheat'],
                'About 12 pieces of today’s unsold kuih, sweet and savoury. You’ll find out which at pickup.', type: ListingType::Bundle, picture: 'surprise-bag'),

            // Kopi Saigon Merbok (coffee shop)
            'latte' => self::item('kopisaigon', 'Iced latte', 900, 450, ListingCategory::Drinks, 'iced-latte', ['milk'],
                'Iced latte made to order before closing.'),
            'spanishlatte' => self::item('kopisaigon', 'Iced Spanish latte', 1100, 550, ListingCategory::Drinks, 'spanish-latte', ['milk'],
                'Espresso over ice with sweetened condensed milk, made to order before closing.', picture: 'iced-drink'),
            'matchalatte' => self::item('kopisaigon', 'Iced matcha latte', 1200, 600, ListingCategory::Drinks, 'matcha-latte', ['milk'],
                'Matcha with fresh milk over ice, made to order before closing.', picture: 'iced-drink'),
            'saigoncoffee' => self::item('kopisaigon', 'Vietnamese iced coffee', 800, 400, ListingCategory::Drinks, 'vietnamese-coffee', ['milk'],
                'Strong drip coffee with condensed milk over ice.', picture: 'iced-drink'),
            'tehtarik' => self::item('kopisaigon', 'Teh tarik × 2', 500, 250, ListingCategory::Drinks, 'teh-tarik', ['milk'],
                'Two cups of hot pulled milk tea, the last of today’s pot.', picture: 'hot-drink'),

            // Merbok Roasted (grilled chicken rice at the main gate)
            'nasiayam' => self::item('merbokroasted', 'Nasi ayam set (2 pax)', 1300, 650, ListingCategory::Meals, 'nasi-ayam', ['soy'],
                'Chicken rice for two with soup and sambal.'),
            'ayampanggang' => self::item('merbokroasted', 'Nasi ayam panggang', 900, 450, ListingCategory::Meals, 'nasi-ayam-panggang', ['soy'],
                'Grilled chicken on rice with soup, cucumber and chilli sauce.', picture: 'rice-meal'),
            'nasilemak' => self::item('merbokroasted', 'Nasi lemak ayam goreng', 800, 400, ListingCategory::Meals, 'nasi-lemak', ['egg', 'nuts', 'seafood'],
                'Coconut rice with fried chicken, egg, peanuts, anchovies and sambal.', picture: 'rice-meal'),
            'meegoreng' => self::item('merbokroasted', 'Mee goreng mamak', 700, 350, ListingCategory::Meals, 'mee-goreng', ['egg', 'wheat', 'soy', 'seafood'],
                'Fried yellow noodles with egg, tofu and prawn fritters.', picture: 'noodles'),
            'dinnerbundle' => self::item('merbokroasted', 'Dinner Surprise Bundle', 1500, 600, ListingCategory::Meals, 'dinner-bundle', ['egg', 'wheat', 'soy', 'seafood'],
                'A rice or noodle meal with a side and a drink from what’s left at closing. You’ll find out which at pickup.', type: ListingType::Bundle, picture: 'surprise-bag'),

            // Se.Bunga Kafe UiTM (café on campus)
            'sandwich' => self::item('sebunga', 'Sandwich & pastry pack', 2400, 900, ListingCategory::Bakery, 'sandwich-pack', $baked,
                'Two sandwiches and two pastries from today’s display.'),
            'nasicampur' => self::item('sebunga', 'Nasi campur', 800, 400, ListingCategory::Meals, 'nasi-campur', ['soy', 'seafood'],
                'Rice with a chicken dish and two vegetable dishes from today’s lunch counter.'),
            'nasigoreng' => self::item('sebunga', 'Nasi goreng kampung', 750, 350, ListingCategory::Meals, 'nasi-goreng', ['egg', 'soy', 'seafood'],
                'Village-style fried rice with anchovies, kangkung and a fried egg.', picture: 'rice-meal'),
            'bihungoreng' => self::item('sebunga', 'Bihun goreng', 600, 300, ListingCategory::Meals, 'bihun-goreng', ['egg', 'soy'],
                'Fried rice vermicelli with vegetables and egg, cooked at lunch.', picture: 'noodles'),
            'bananacake' => self::item('sebunga', 'Banana cake slice × 2', 1000, 500, ListingCategory::Bakery, 'banana-cake', $baked,
                'Two thick slices of the café’s banana cake.', picture: 'cake-slice'),
            'cafebundle' => self::item('sebunga', 'Café Surprise Bundle', 1500, 600, ListingCategory::Bakery, 'cafe-bundle', $baked,
                'A mix of today’s unsold sandwiches, cakes and pastries. You’ll find out what’s inside at pickup.', type: ListingType::Bundle, picture: 'surprise-bag'),
        ];
    }

    /**
     * One item, ready to become a listing: everything from items() except
     * `shop`, `photo` and `picture`, plus the `photo_path` to show.
     *
     * @return array<string, mixed>
     */
    public static function listingData(string $key): array
    {
        $item = self::items()[$key];

        $item['photo_path'] = DemoPhotos::path($item['photo'], $item['picture']);
        unset($item['shop'], $item['photo'], $item['picture']);

        return $item;
    }

    /** Which shop sells the item ("bondamawar"). */
    public static function shopOf(string $key): string
    {
        return self::items()[$key]['shop'];
    }

    /**
     * Every file name a real photo can have in docs/demo-food/real: one per
     * item, plus the general drawings several items share.
     *
     * @return array<string, string> file name (no ending) => what it is shown on
     */
    public static function photoNames(): array
    {
        $names = [];
        $shared = [];

        foreach (self::items() as $item) {
            $names[$item['photo']] = $item['title'].' · '.self::SHOPS[$item['shop']];

            if ($item['picture'] !== null) {
                $shared[$item['picture']] = 'Every item drawn as "'.str_replace('-', ' ', $item['picture']).'" that has no photo of its own';
            }
        }

        return $names + $shared;
    }

    /**
     * @param  list<string>  $allergens
     * @return array<string, mixed>
     */
    private static function item(
        string $shop,
        string $title,
        int $originalSen,
        int $priceSen,
        ListingCategory $category,
        string $photo,
        array $allergens,
        string $description,
        ListingType $type = ListingType::Regular,
        ?string $picture = null,
    ): array {
        return [
            'shop' => $shop,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'photo' => $photo,
            'picture' => $picture,
            'original_price_sen' => $originalSen,
            'price_sen' => $priceSen,
            // Bonda Mawar is the demo shop with a halal certificate on file; the others are Muslim-owned.
            'halal_status' => $shop === 'bondamawar' ? HalalStatus::Certified : HalalStatus::MuslimOwned,
            'allergens' => $allergens,
        ];
    }
}
