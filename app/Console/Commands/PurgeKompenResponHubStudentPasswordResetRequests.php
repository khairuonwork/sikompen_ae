<?php

namespace App\Console\Commands;

use App\Models\KompenResponHubActiveStudent;
use App\Models\KompenResponHubStudentPasswordResetRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sikompen:purge-student-password-reset-requests')]
#[Description('Remove expired, completed, and inactive student password reset requests.')]
class PurgeKompenResponHubStudentPasswordResetRequests extends Command
{
    public function handle(): int
    {
        $threshold = now('Asia/Jakarta')->subDays(7);
        $activeStudents = KompenResponHubActiveStudent::query()
            ->fromActiveRosterReference()
            ->select('nim');
        $deletedCount = KompenResponHubStudentPasswordResetRequest::query()
            ->where(function ($query) use ($activeStudents, $threshold): void {
                $query
                    ->whereNotIn('nim', $activeStudents)
                    ->orWhere('fulfilled_at', '<=', $threshold)
                    ->orWhere(function ($expiredQuery) use ($threshold): void {
                        $expiredQuery
                            ->whereNull('fulfilled_at')
                            ->where('expires_at', '<=', $threshold);
                    });
            })
            ->delete();

        $this->components->info("{$deletedCount} permintaan reset password mahasiswa kedaluwarsa atau tidak lagi valid dihapus.");

        return self::SUCCESS;
    }
}
