<?php

namespace App\Http\Controllers;

use App\Domain\Restaurant\Contracts\RestaurantRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles QR redirect: GET /page/r/{uuid} on the main app domain.
 * Looks up restaurant by public uuid and redirects to the subdomain URL.
 * No internal id in any response; path and lookup use uuid only.
 */
class QrRedirectController extends Controller
{
    public function __construct(
        private readonly RestaurantRepositoryInterface $restaurantRepository
    ) {}

    /**
     * Redirect to the restaurant's subdomain. 404 if restaurant not found for uuid.
     */
    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);

        if ($restaurant === null) {
            abort(404);
        }

        $scheme = app()->environment('production') ? 'https' : $request->getScheme();
        $domain = config('app.restaurant_domain', 'localhost');
        $url = $scheme . '://' . $restaurant->slug . '.' . $domain . '/';

        return redirect()->away($url, 302);
    }
}
