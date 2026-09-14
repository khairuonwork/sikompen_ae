<?php

namespace App\Http\Controllers;

use App\Actions\SiAdminProxy\SiAdminProxyAccess;
use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubLandingController extends Controller
{
    public function __invoke(Request $request, SiAdminProxyAccess $access): Response
    {
        $isStandaloneMode = ! config('si-admin-proxy.enabled');
        $isInitialAdminSetupAvailable = $isStandaloneMode && ! KompenResponHubAdmin::query()->exists();
        $isAdminSetupOpen = ! $isInitialAdminSetupAvailable
            && $isStandaloneMode
            && KompenResponHubAdminSetupWindow::query()->find(1)?->isOpen();

        return Inertia::render('welcome', [
            'isAdminAuthenticated' => $access->hasAdminAccess($request),
            'isInitialAdminSetupAvailable' => $isInitialAdminSetupAvailable,
            'isAdminSetupOpen' => $isAdminSetupOpen,
        ]);
    }
}
