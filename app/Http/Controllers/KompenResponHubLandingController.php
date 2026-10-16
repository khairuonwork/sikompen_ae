<?php

namespace App\Http\Controllers;

use App\Models\KompenResponHubAdmin;
use App\Models\KompenResponHubAdminSetupWindow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KompenResponHubLandingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $isInitialAdminSetupAvailable = ! KompenResponHubAdmin::query()
            ->where('is_emergency_admin', false)
            ->exists();
        $isAdminSetupOpen = ! $isInitialAdminSetupAvailable
            && KompenResponHubAdminSetupWindow::query()->find(1)?->isOpen();

        return Inertia::render('welcome', [
            'isAdminAuthenticated' => $request->user('admin') instanceof KompenResponHubAdmin,
            'isInitialAdminSetupAvailable' => $isInitialAdminSetupAvailable,
            'isAdminSetupOpen' => $isAdminSetupOpen,
        ]);
    }
}
