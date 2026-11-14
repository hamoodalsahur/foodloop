{{--
    Create or edit a listing (mockup c09). Data from App\Http\Controllers\Seller\ListingController:
    `listing` (new or saved), `shop`, `locked` (someone has reserved), `taken`, `canCertify`.
    Rules: App\Http\Requests\Seller\SaveListingRequest and App\Services\SellerListingService.
--}}
@php
    use App\Enums\HalalStatus;
    use App\Enums\ListingCategory;
    use App\Enums\ListingStatus;
    use App\Enums\ListingType;
    use App\Support\PickupWindow;

    $isNew = ! $listing->exists;
    $status = $listing->status;
    $closed = $status === ListingStatus::Closed;
    $canPublish = $shop->isApproved();
    $title = $isNew ? 'New listing' : 'Edit listing';

    $hints = [
        'regular' => 'One specific item',
        'flash' => 'Short, time-limited price drop',
        'bundle' => 'Mixed items, contents vary',
    ];
    $allergens = config('foodloop.allergens');
    $chosenAllergens = old('allergens', $listing->allergens ?? []);
    $type = old('type', $listing->type?->value ?? 'regular');

    // Starting values for the live preview (Alpine reads these; without JavaScript the preview shows them as they are).
    $preview = [
        'title' => old('title', $listing->title ?? ''),
        'was' => old('original_price', $listing->original_price_sen ? number_format($listing->original_price_sen / 100, 2, '.', '') : ''),
        'price' => old('price', $listing->price_sen ? number_format($listing->price_sen / 100, 2, '.', '') : ''),
        'qty' => old('quantity', $listing->quantity_total ?? 5),
        'type' => $type,
    ];
    $typeLabels = collect(ListingType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]);
@endphp

<x-layouts.seller :title="$title" active="listings">
    <x-slot:subtitle>
        <a href="{{ route('seller.listings.index') }}" class="font-semibold underline underline-offset-4">← All listings</a>
        @unless ($isNew) · @include('seller.partials.listing-status', ['listing' => $listing]) @endunless
    </x-slot:subtitle>

    @include('seller.partials.approval-notice')
    @include('seller.partials.flash')

    @if ($closed)
        <x-notice class="mb-6">This listing has closed, so it can’t be changed. Leftovers are handled on the <a href="{{ route('seller.donations.index') }}" class="font-semibold underline">Donations</a> page.</x-notice>
    @elseif ($locked)
        <x-notice class="mb-6">
            <strong>{{ $taken }} {{ str('portion')->plural($taken) }} reserved</strong>, so the price, quantity, pickup time and halal status are locked:
            students keep the deal they booked. You can still change the photo, name and description, or pause the listing.
        </x-notice>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start"
         x-data="{ ...@js($preview), types: @js($typeLabels),
                   get off() { const w = parseFloat(this.was), p = parseFloat(this.price); return w > 0 && p >= 0 && p < w ? Math.round((1 - p / w) * 100) : 0 } }">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $isNew ? route('seller.listings.store') : route('seller.listings.update', $listing) }}"
              class="space-y-6 rounded-3xl bg-white p-5 md:p-6">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <fieldset @disabled($closed)>
                <legend class="font-display text-lg font-bold">Type</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    @foreach (ListingType::cases() as $option)
                        <label class="flex min-h-11 cursor-pointer flex-col gap-1 rounded-2xl border-2 border-forest p-3 has-[:checked]:bg-marigold has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-marigold">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="type" value="{{ $option->value }}" x-model="type" @checked($type === $option->value) class="size-4 accent-forest">
                                <span class="font-display font-bold">{{ $option->label() }}</span>
                            </span>
                            <span class="text-xs">{{ $hints[$option->value] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <x-form.input name="title" label="Name" :value="$listing->title" maxlength="80" required x-model="title" :disabled="$closed" />
                <x-form.select name="category" label="Category" :value="$listing->category?->value" :disabled="$closed"
                               :options="collect(ListingCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" />
            </div>

            <div>
                <p class="text-sm font-semibold">Photo</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-4 rounded-2xl border-2 border-dashed border-line bg-cream p-3">
                    <x-food-photo :path="$listing->photo_path" :alt="$listing->photo_path ? 'Current photo' : 'No photo yet'" />
                    <div class="min-w-0 flex-1">
                        <label for="photo" class="text-sm font-semibold">{{ $listing->photo_path ? 'Change photo' : 'Add a photo' }}</label>
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" @disabled($closed)
                               @error('photo') aria-invalid="true" aria-describedby="photo-error" @else aria-describedby="photo-hint" @enderror
                               class="mt-1 block w-full text-sm file:mr-3 file:min-h-11 file:rounded-xl file:border-2 file:border-forest file:bg-white file:px-4 file:font-semibold">
                        @error('photo')
                            <p id="photo-error" class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p>
                        @else
                            <p id="photo-hint" class="mt-1 text-xs text-muted">A real photo of today’s food builds trust. JPG, PNG or WebP, up to 5 MB.</p>
                        @enderror
                    </div>
                </div>
            </div>

            <x-form.textarea name="description" label="What customers might get" :value="$listing->description" rows="3" maxlength="500" :disabled="$closed"
                             hint="For a Surprise Bundle, say what is usually inside." />

            <fieldset @disabled($locked || $closed) class="space-y-4 disabled:opacity-60">
                <legend class="sr-only">Price, quantity and pickup</legend>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end">
                    <x-form.input name="original_price" label="Normal price (RM)" inputmode="decimal" :value="$preview['was']" x-model="was" required />
                    <x-form.input name="price" label="FoodLoop price (RM)" inputmode="decimal" :value="$preview['price']" x-model="price" required />
                    <x-form.input name="quantity" label="Portions" type="number" min="1" :max="config('foodloop.max_portions_per_listing')" :value="$listing->quantity_total" x-model="qty" required />
                    <p class="flex min-h-12 items-center justify-center rounded-xl bg-marigold px-4 font-display text-xl font-bold" aria-live="polite">
                        <span x-text="off > 0 ? '−' + off + '%' : '0%'">−{{ $listing->discount_percent ?? 0 }}%</span>
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <x-form.input name="pickup_date" label="Pickup day" type="date" class="col-span-2 sm:col-span-1"
                                  :value="$listing->pickup_start?->format('Y-m-d')" :min="today()->format('Y-m-d')" required />
                    <x-form.input name="pickup_start" label="Pickup starts" type="time" :value="$listing->pickup_start?->format('H:i')" required />
                    <x-form.input name="pickup_end" label="Pickup ends" type="time" :value="$listing->pickup_end?->format('H:i')" required />
                </div>
                <p class="text-sm text-muted">Reservations close when pickup starts. Anything not collected 15 minutes after pickup ends counts as a no-show.</p>
            </fieldset>

            <fieldset @disabled($locked || $closed) class="disabled:opacity-60">
                <legend class="font-display text-lg font-bold">Halal status <span class="font-sans text-xs font-bold uppercase tracking-wider text-danger">Required</span></legend>
                @php($halal = old('halal_status', $listing->halal_status?->value))
                <div class="mt-2 space-y-1">
                    @foreach (HalalStatus::cases() as $option)
                        @php($blocked = $option === HalalStatus::Certified && ! $canCertify)
                        <label class="flex min-h-11 items-center gap-3 text-sm @if ($blocked) text-muted @endif">
                            <input type="radio" name="halal_status" value="{{ $option->value }}" @checked($halal === $option->value) @disabled($blocked) class="size-4 accent-forest">
                            {{ $option->label() }}
                            @if ($option === HalalStatus::Certified)
                                <span class="text-xs">{{ $canCertify ? '· certificate on file' : '· needs an approved JAKIM certificate' }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @error('halal_status') <p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p> @enderror
            </fieldset>

            <fieldset @disabled($closed)>
                <legend class="font-display text-lg font-bold">May contain</legend>
                <div class="mt-2 flex flex-wrap gap-x-6">
                    @foreach ($allergens as $allergen)
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" name="allergens[]" value="{{ $allergen }}" @checked(in_array($allergen, $chosenAllergens, true)) class="size-4 accent-forest">
                            {{ ucfirst($allergen) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="flex items-start gap-3 rounded-2xl bg-forest p-4 text-sm text-cream">
                <input type="hidden" name="offer_leftovers" value="0">
                <input type="checkbox" name="offer_leftovers" value="1" @checked(old('offer_leftovers', $listing->offer_leftovers)) @disabled($closed) class="mt-0.5 size-4 accent-marigold">
                <span><strong class="text-marigold">Offer leftovers to a food bank.</strong> If portions are left when pickup ends, we offer them to our partner automatically.</span>
            </label>

            @unless ($closed)
                <div class="flex flex-wrap justify-end gap-3 border-t border-line pt-5">
                    @if ($isNew || $status === ListingStatus::Draft)
                        <x-button type="submit" name="action" value="draft" variant="outline">Save draft</x-button>
                        @if ($canPublish)
                            <x-button type="submit" name="action" value="publish">Publish listing</x-button>
                        @endif
                    @elseif ($status === ListingStatus::Paused)
                        <x-button type="submit" name="action" value="draft" variant="outline">Save, keep paused</x-button>
                        @if ($canPublish)
                            <x-button type="submit" name="action" value="publish">Save and put live</x-button>
                        @endif
                    @else
                        <x-button type="submit" name="action" value="draft">Save changes</x-button>
                    @endif
                </div>
                @unless ($canPublish)
                    <p class="text-right text-sm text-muted">Publishing unlocks once an admin approves your shop.</p>
                @endunless
            @endunless
        </form>

        <aside class="space-y-4 lg:sticky lg:top-6">
            <div>
                <p class="text-[0.6875rem] font-bold uppercase tracking-widest text-muted">Customer preview</p>
                <div class="mt-2 flex items-center gap-3 rounded-3xl bg-white p-3">
                    <x-food-photo :path="$listing->photo_path" alt="" />
                    <div class="min-w-0 flex-1">
                        <p class="text-[0.6875rem] font-bold uppercase tracking-wider text-muted" x-text="types[type]">{{ $typeLabels[$type] }}</p>
                        <p class="truncate font-bold" x-text="title || 'Your listing name'">{{ $preview['title'] ?: 'Your listing name' }}</p>
                        <p class="text-xs text-muted">{{ $shop->business_name }} · <span x-text="qty">{{ $preview['qty'] }}</span> left</p>
                    </div>
                    <div class="flex min-w-16 flex-col items-center rounded-xl bg-forest px-2.5 py-2 text-cream">
                        <span class="font-display font-bold">RM<span x-text="price || '0'">{{ $preview['price'] ?: '0' }}</span></span>
                        <s class="text-xs text-mist">RM<span x-text="was || '0'">{{ $preview['was'] ?: '0' }}</span></s>
                    </div>
                </div>
                <p class="mt-2 text-sm text-muted">This is how it looks on the deals page. It goes live when you publish and disappears when it sells out or pickup starts.</p>
            </div>

            @unless ($isNew)
                <x-card class="space-y-3">
                    <h2 class="font-display text-lg font-bold">This listing</h2>
                    @if ($listing->isPublished())
                        <x-button href="{{ route('listings.show', $listing) }}" variant="outline" class="w-full">View as a student</x-button>
                    @endif
                    @if (in_array($status, [ListingStatus::Live, ListingStatus::SoldOut], true))
                        <form method="POST" action="{{ route('seller.listings.pause', $listing) }}">
                            @csrf
                            <x-button type="submit" variant="outline" class="w-full">Pause (take off the deals page)</x-button>
                        </form>
                    @endif
                    @if ($status === ListingStatus::Draft && ! $locked)
                        <form method="POST" action="{{ route('seller.listings.destroy', $listing) }}"
                              x-data="{ sure: false }" @submit="if (! sure) { $event.preventDefault(); sure = true }">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="outline" class="w-full">
                                <span x-text="sure ? 'Tap again to delete' : 'Delete draft'">Delete draft</span>
                            </x-button>
                        </form>
                    @endif
                    @if ($status === ListingStatus::Closed)
                        <p class="text-sm text-muted">Closed {{ PickupWindow::withDay($listing->pickup_start, $listing->pickup_end) }}.</p>
                    @endif
                </x-card>
            @endunless
        </aside>
    </div>
</x-layouts.seller>
