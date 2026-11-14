<?php

// Real photos for the demo: a file in docs/demo-food/real/ with a product's
// name replaces that product's drawn picture (docs/demo-food/real/README.md).

use App\Models\Listing;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    // A stand-in for docs/demo-food: the drawn pictures plus an empty "real" folder.
    $this->folder = storage_path('framework/testing/demo-food-'.uniqid());
    File::copyDirectory(base_path('docs/demo-food'), $this->folder);
    File::cleanDirectory($this->folder.'/real');
    config(['foodloop.demo_photo_dir' => $this->folder]);

    $this->addPhoto = fn (string $file) => File::put($this->folder.'/real/'.$file, 'a real photo: '.$file);
});

afterEach(function () {
    File::deleteDirectory($this->folder);
});

it('uses a real photo for a product when one is provided', function () {
    ($this->addPhoto)('curry-puffs.jpg');
    $this->seed(DatabaseSeeder::class);

    $puffs = Listing::firstWhere('title', 'Curry puffs × 4');

    expect($puffs->photo_path)->toBe('listings/demo/curry-puffs.jpg')
        ->and(Storage::disk('public')->get('listings/demo/curry-puffs.jpg'))->toBe('a real photo: curry-puffs.jpg');

    $this->get('/?within=any&halal=0')->assertSee('/storage/listings/demo/curry-puffs.jpg', false);
});

it('keeps the drawn picture for every product that has no real photo', function () {
    ($this->addPhoto)('curry-puffs.jpg');
    $this->seed(DatabaseSeeder::class);

    expect(Listing::firstWhere('title', 'Iced latte')->photo_path)->toBe('listings/demo/iced-latte.svg')
        ->and(Listing::where('title', 'Bakery Surprise Bundle')->pluck('photo_path')->unique()->all())->toBe(['listings/demo/bakery-bundle.svg']);
});

it('goes back to the drawn picture when a real photo is taken away again', function () {
    // Left over from an earlier seed that had a real photo.
    Storage::disk('public')->put('listings/demo/curry-puffs.jpg', 'an old photo');
    Storage::disk('public')->put('listings/a-sellers-own-upload.jpg', 'not demo data');

    $this->seed(DatabaseSeeder::class);

    expect(Listing::firstWhere('title', 'Curry puffs × 4')->photo_path)->toBe('listings/demo/curry-puffs.svg');
    Storage::disk('public')->assertMissing('listings/demo/curry-puffs.jpg');
    Storage::disk('public')->assertExists('listings/a-sellers-own-upload.jpg');
});

it('accepts jpg, jpeg, png and webp', function (string $type) {
    ($this->addPhoto)("iced-latte.{$type}");
    $this->seed(DatabaseSeeder::class);

    expect(Listing::firstWhere('title', 'Iced latte')->photo_path)->toBe("listings/demo/iced-latte.{$type}");
})->with(['jpg', 'jpeg', 'png', 'webp']);

it('uses the real photo on every listing of that product, including the older ones', function () {
    ($this->addPhoto)('bakery-bundle.webp');
    $this->seed(DatabaseSeeder::class);

    expect(Listing::where('title', 'Bakery Surprise Bundle')->pluck('photo_path')->unique()->all())
        ->toBe(['listings/demo/bakery-bundle.webp']);
});

it('ignores files that are not pictures or not named after a product', function () {
    ($this->addPhoto)('curry-puffs.txt');
    ($this->addPhoto)('my-holiday.jpg');
    $this->seed(DatabaseSeeder::class);

    expect(Listing::firstWhere('title', 'Curry puffs × 4')->photo_path)->toBe('listings/demo/curry-puffs.svg')
        ->and(Listing::where('photo_path', 'like', '%my-holiday%')->count())->toBe(0);

    Listing::all()->each(fn (Listing $listing) => Storage::disk('public')->assertExists($listing->photo_path));
});

it('documents the six product names, and each has a drawn picture to fall back on', function () {
    $readme = File::get(base_path('docs/demo-food/real/README.md'));

    foreach (['bakery-bundle', 'croissants', 'curry-puffs', 'iced-latte', 'sandwich-pack', 'nasi-ayam'] as $name) {
        expect($readme)->toContain("`{$name}.jpg`");
        expect(File::exists(base_path("docs/demo-food/{$name}.svg")))->toBeTrue();
    }
});
