<?php

namespace App\Http\Controllers;

use App\Models\App;
use App\Models\Store;
use App\Services\StoreAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreAppPageController extends Controller
{
    public function __construct(
        protected AppController $appController,
        protected StoreAppService $storeApps,
    ) {}

    /**
     * List apps for a store (Inertia page).
     */
    public function index(Store $store): Response
    {
        $store->loadMissing(['checklistItems', 'walletConnection']);
        $apps = $this->storeApps->listForStore($store);

        return Inertia::render('stores/apps/Index', [
            'store' => $store,
            'apps' => $apps,
        ]);
    }

    /**
     * Store a new app (Inertia form submission).
     */
    public function store(Request $request, Store $store): RedirectResponse|Response
    {
        $response = $this->appController->store($request, $store);
        $status = $response->getStatusCode();
        $data = $response->getData(true);

        if ($status === 201 && isset($data['data']['id'])) {
            return redirect()->to("/stores/{$store->id}/apps/{$data['data']['id']}");
        }
        if ($status === 422 && isset($data['errors'])) {
            return redirect()->back()->withErrors($data['errors'])->withInput();
        }
        $message = $data['message'] ?? 'Failed to create app.';

        return redirect()->back()->with('error', $message)->withInput();
    }

    /**
     * Show create app form (Inertia page).
     */
    public function create(Store $store): Response
    {
        $store->loadMissing(['checklistItems', 'walletConnection']);
        $apps = $this->storeApps->listForStore($store);

        return Inertia::render('stores/apps/Create', [
            'store' => $store,
            'apps' => $apps,
        ]);
    }

    /**
     * Show a single app (Inertia page).
     */
    public function show(Store $store, App $app): Response
    {
        if ($app->store_id !== $store->id) {
            abort(404);
        }
        $store->loadMissing(['checklistItems', 'walletConnection']);
        $appData = $this->storeApps->getForStore($store, $app);
        if ($appData === null) {
            abort(404);
        }
        $apps = $this->storeApps->listForStore($store);

        return Inertia::render('stores/apps/Show', [
            'store' => $store,
            'app' => $appData,
            'apps' => $apps,
        ]);
    }
}
