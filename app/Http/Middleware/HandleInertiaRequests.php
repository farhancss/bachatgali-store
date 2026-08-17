<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Props shared with every Inertia page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'store' => [
                'currencySymbol' => config('bachatgali.currency.symbol'),
                'freeDeliveryFrom' => config('bachatgali.delivery.free_threshold'),
                'codOnly' => true,
                'supportWhatsApp' => config('bachatgali.support.whatsapp'),
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
        ];
    }
}
