<?php

namespace App\Observers;

use App\Filament\Resources\OldJewelleryRequests\OldJewelleryRequestResource;
use App\Models\OldJewelleryBid;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\BiddingUpdateNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class OldJewelleryBidObserver
{
    public function created(OldJewelleryBid $bid): void
    {
        $request = $bid->request;
        if (! $request) {
            return;
        }

        // 1. Notify admins if placed by a vendor
        if ($bid->bidder_type === 'vendor') {
            $vendor = $bid->vendor;
            $vendorName = $vendor?->name ?? 'A vendor';

            $admins = User::whereHas('roles', fn ($q) => $q->whereNotIn('name', Vendor::ACCESS_ROLES))
                ->whereNotNull('password')
                ->get();

            if ($admins->isNotEmpty()) {
                Notification::make()
                    ->title("New bid on {$request->request_number}")
                    ->body("{$vendorName} placed a bid of ₹" . number_format((float) $bid->amount, 2))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->warning()
                    ->actions([
                        Action::make('view')
                            ->label('View Request')
                            ->url(OldJewelleryRequestResource::getUrl('view', ['record' => $request])),
                    ])
                    ->sendToDatabase($admins);
            }
        }

        // 2. Notify the customer
        $customer = $request->user;
        if ($customer) {
            $previousBids = $request->bids()->where('id', '!=', $bid->id);
            $hasPrevious = $previousBids->exists();
            $isHigher = $hasPrevious && (float) $bid->amount > (float) $previousBids->max('amount');

            try {
                $customer->notify(new BiddingUpdateNotification(
                    $request,
                    $isHigher ? 'higher_bid' : 'new_bid',
                    amount: (float) $bid->amount
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
