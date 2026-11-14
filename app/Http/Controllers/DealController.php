<?php

namespace App\Http\Controllers;

use App\Enums\ListingCategory;
use App\Queries\DealsQuery;
use App\Support\DealFilters;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The deals page (the home page): what can be rescued right now.
 * Open to everyone, signed in or not.
 */
class DealController extends Controller
{
    public function index(Request $request, DealsQuery $deals): Response
    {
        $filters = DealFilters::fromRequest($request);

        try {
            $inRange = $deals->inRange($filters);
        } catch (QueryException $exception) {
            // The database didn't answer. Show the "Couldn't load deals" state
            // instead of a crash page, and keep the details in the log.
            report($exception);

            return response()->view('deals.index', ['filters' => $filters, 'failed' => true], 503);
        }

        return response()->view('deals.index', [
            'filters' => $filters,
            'failed' => false,
            'listings' => $deals->results($inRange, $filters),
            'bundlesLeft' => $deals->bundlesLeft($inRange),
            'closingSoon' => $deals->closingSoon($inRange),
            'categories' => ListingCategory::cases(),
        ]);
    }
}
