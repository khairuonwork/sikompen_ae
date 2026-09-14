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
        $isInitialAdminSetupAvailable = ! KompenResponHubAdmin::query()->exists();
        $isAdminSetupOpen = ! $isInitialAdminSetupAvailable
            && KompenResponHubAdminSetupWindow::query()->find(1)?->isOpen();

        return Inertia::render('welcome', [
            'isAdminAuthenticated' => $access->hasAdminAccess($request),
            'isInitialAdminSetupAvailable' => $isInitialAdminSetupAvailable,
            'isAdminSetupOpen' => $isAdminSetupOpen,
        ]);
    }
}
