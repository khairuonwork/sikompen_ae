<?php

namespace App\Http\Controllers;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubLandingController extends Controller
{
    public function __invoke(): Response
    {
        $isInitialAdminSetupAvailable = ! KompenResponHubAdmin::query()->exists();
        $isAdminSetupOpen = ! $isInitialAdminSetupAvailable
            && KompenResponHubAdminSetupWindow::query()->find(1)?->isOpen();

        return Inertia::render('welcome', [
            'isAdminAuthenticated' => Auth::guard('admin')->check(),
            'isInitialAdminSetupAvailable' => $isInitialAdminSetupAvailable,
            'isAdminSetupOpen' => $isAdminSetupOpen,
        ]);
    }
}
