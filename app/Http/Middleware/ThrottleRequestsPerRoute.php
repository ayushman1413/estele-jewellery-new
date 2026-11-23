<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Laravel's plain `throttle:10,1` keys its counter on the visitor alone (the
 * signed-in user's id, or the guest's IP), so every route throttled that way
 * shared ONE counter: a few search suggestions while typing, a pincode
 * lookup and an add-to-cart used up the 10 "Place order" attempts and the
 * order was refused with 429 Too Many Attempts. Worse, if that shared
 * counter was started by an hourly limit (newsletter, reviews), checkout
 * stayed blocked for the hour.
 *
 * Adding the route to the key gives every route its own allowance, which is
 * what each `throttle:` line in routes/*.php says. Named limiters
 * (RateLimiter::for in AppServiceProvider) key themselves and are unchanged.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $scope = $route ? implode('|', $route->methods()).' '.$route->getDomain().$route->uri() : '';

        return sha1($scope.'#'.parent::resolveRequestSignature($request));
    }
}
