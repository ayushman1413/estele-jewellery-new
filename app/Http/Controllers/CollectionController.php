<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CollectionController extends Controller
{
    public function index()
    {
        $collections = Cache::tags(['collections'])->remember('collections.index', now()->addHours(3), function () {
            return Collection::active()->ordered()->with('media')->get();
        });

        return view('collections.index', compact('collections'));
    }

    public function show(Collection $collection, Request $request)
    {
        abort_unless($collection->is_active, 404);

        $sort = $request->query('sort', 'featured');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $inStock = $request->boolean('in_stock');
        $page = $request->query('page', 1);

        $cacheKey = 'collection.'.$collection->id.'.products.'.md5(json_encode([
            $sort, $minPrice, $maxPrice, $inStock, $page,
        ]));

        $products = Cache::tags(['collection:'.$collection->id])->remember(
            $cacheKey,
            now()->addMinutes(15),
            function () use ($collection, $sort, $minPrice, $maxPrice, $inStock) {
                $query = $collection->products()
                    ->with('media')
                    ->withCount('approvedReviews')
                    ->withAvg('approvedReviews', 'rating')
                    ->where('is_active', true);

                if ($minPrice !== null && $minPrice !== '') {
                    $query->where('price', '>=', (float) $minPrice);
                }
                if ($maxPrice !== null && $maxPrice !== '') {
                    $query->where('price', '<=', (float) $maxPrice);
                }
                if ($inStock) {
                    $query->where(function ($q) {
                        $q->where(function ($plain) {
                            $plain->doesntHave('variants')->where('stock_quantity', '>', 0);
                        })->orWhereHas('variants', fn ($v) => $v->where('stock_quantity', '>', 0));
                    });
                }

                match ($sort) {
                    'price_asc' => $query->orderBy('price'),
                    'price_desc' => $query->orderBy('price', 'desc'),
                    'newest' => $query->latest('products.created_at'),
                    default => $query->orderByDesc('products.is_featured')->orderBy('products.id'),
                };

                return $query->paginate(24)->withQueryString();
            }
        );

        return view('collections.show', compact(
            'collection', 'products', 'sort', 'minPrice', 'maxPrice', 'inStock'
        ));
    }
}
