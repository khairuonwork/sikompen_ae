<?php

namespace App\Actions\KompenResponHub;

use App\Models\KompenResponHubPeriodCutoff;

class EnsureKompenResponHubPeriodIsOpen
{
    public function isClosed(string $period): bool
    {
        return KompenResponHubPeriodCutoff::query()
            ->where('periode_semester', $period)
            ->whereNotNull('closed_at')
            ->exists();
    }
}
