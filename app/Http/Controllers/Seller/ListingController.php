<?php

namespace App\Http\Controllers\Seller;

use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Exceptions\SellerActionRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SaveListingRequest;
use App\Models\Listing;
use App\Services\SellerListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * A seller's own listings: the list, the create / edit form (mockup c09),
 * publish, pause, delete a draft, and offer leftovers to the food bank.
 * The rules live in App\Services\SellerListingService.
 */
class ListingController extends Controller
{
    public function __construct(private SellerListingService $listings) {}

    public function index(Request $request): View
    {
        $shop = $request->user()->seller;
        abort_if($shop === null, 404);

        $all = $shop->listings()->latest('pickup_start')->get();

        return view('seller.listings.index', [
            'groups' => [
                'On the deals page' => $all->whereIn('status', [ListingStatus::Live, ListingStatus::SoldOut])->sortBy('pickup_start'),
                'Paused' => $all->where('status', ListingStatus::Paused),
                'Drafts' => $all->where('status', ListingStatus::Draft),
                'Closed' => $all->where('status', ListingStatus::Closed)->take(20),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Listing::class);

        // A sensible start: today, from the next half hour, for two hours.
        $start = now()->addMinutes(30 - now()->minute % 30)->addMinutes(30)->startOfMinute();
        // A window can't run past midnight (one pickup day), so late in the evening suggest tomorrow's lunch.
        if (! $start->copy()->addHours(2)->isSameDay($start)) {
            $start = now()->addDay()->setTime(12, 0);
        }

        $listing = new Listing([
            'type' => 'regular',
            'category' => 'meals',
            'halal_status' => $request->user()->seller->halal_claim === HalalStatus::Certified
                && $request->user()->seller->hasApprovedHalalCertificate()
                    ? HalalStatus::Certified : HalalStatus::MuslimOwned,
            'allergens' => [],
            'offer_leftovers' => true,
            'quantity_total' => 5,
            'pickup_start' => $start,
            'pickup_end' => $start->copy()->addHours(2),
        ]);

        return $this->form($request, $listing);
    }

    public function store(SaveListingRequest $request): RedirectResponse
    {
        return $this->persist($request, null);
    }

    public function edit(Request $request, Listing $listing): View
    {
        Gate::authorize('update', $listing);

        return $this->form($request, $listing);
    }

    public function update(SaveListingRequest $request, Listing $listing): RedirectResponse
    {
        return $this->persist($request, $listing);
    }

    public function publish(Listing $listing): RedirectResponse
    {
        Gate::authorize('publish', $listing);

        return $this->attempt($listing, fn () => $this->listings->publish($listing), 'It’s live on the deals page.');
    }

    public function pause(Listing $listing): RedirectResponse
    {
        Gate::authorize('pause', $listing);

        return $this->attempt($listing, fn () => $this->listings->pause($listing),
            'Paused. It’s off the deals page; reservations already made still stand.');
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        Gate::authorize('delete', $listing);

        try {
            $this->listings->deleteDraft($listing);
        } catch (SellerActionRefused $refused) {
            return back()->withErrors(['listing' => $refused->getMessage()]);
        }

        return redirect()->route('seller.listings.index')->with('status', 'Draft deleted.');
    }

    public function offerLeftovers(Listing $listing): RedirectResponse
    {
        Gate::authorize('update', $listing);

        try {
            $donation = $this->listings->offerLeftovers($listing);
        } catch (SellerActionRefused $refused) {
            return back()->withErrors(['listing' => $refused->getMessage()]);
        }

        return back()->with('status', "Offered {$donation->quantity} to {$donation->partner->name}. Thank you!");
    }

    private function persist(SaveListingRequest $request, ?Listing $listing): RedirectResponse
    {
        $shop = $request->user()->seller;
        $wasLive = $listing !== null && in_array($listing->status, [ListingStatus::Live, ListingStatus::SoldOut], true);

        if ($request->wantsToPublish() && $listing !== null) {
            Gate::authorize('publish', $listing);
        }

        try {
            $saved = $this->listings->save($shop, $listing, $request->listingData(), $request->file('photo'), $request->wantsToPublish());
        } catch (SellerActionRefused $refused) {
            return back()->withInput()->withErrors(['listing' => $refused->getMessage()]);
        }

        $message = match (true) {
            $saved->status === ListingStatus::Live && ! $wasLive => 'Published. Students can reserve it now.',
            $saved->status === ListingStatus::Draft => 'Saved as a draft. Publish it when you’re ready.',
            default => 'Changes saved.',
        };

        return redirect()->route('seller.listings.edit', $saved)->with('status', $message);
    }

    /** Run a seller action and come back to the edit page with the result. */
    private function attempt(Listing $listing, \Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (SellerActionRefused $refused) {
            return back()->withErrors(['listing' => $refused->getMessage()]);
        }

        return redirect()->route('seller.listings.edit', $listing)->with('status', $success);
    }

    private function form(Request $request, Listing $listing): View
    {
        $shop = $request->user()->seller;

        return view('seller.listings.form', [
            'listing' => $listing,
            'shop' => $shop,
            'locked' => $this->listings->isLocked($listing),
            'taken' => $listing->exists ? $this->listings->takenCount($listing) : 0,
            'canCertify' => $shop->hasApprovedHalalCertificate(),
        ]);
    }
}
