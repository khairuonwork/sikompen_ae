<?php

namespace App\Http\Controllers;

use App\Actions\KompenResponHub\RecordKompenResponHubActivity;
use App\Actions\KompenResponHub\RetryKompenResponHubTask;
use App\Actions\KompenResponHub\SikompenAdminAccess;
use App\Http\Requests\RetryKompenResponHubTaskRequest;
use App\Jobs\GenerateKompenResponHubExport;
use App\Jobs\ProcessKompenResponHubActiveStudentImport;
use App\Jobs\ProcessKompenResponHubImport;
use App\Models\KompenResponHubAdmin;
use Illuminate\Http\RedirectResponse;

class KompenResponHubTaskDiagnosticController extends Controller
{
    public function retry(
        RetryKompenResponHubTaskRequest $request,
        SikompenAdminAccess $access,
        RetryKompenResponHubTask $retryTask,
        RecordKompenResponHubActivity $activity,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $access->actor($request);
        $admin = is_int($actor['id'])
            ? KompenResponHubAdmin::query()->find($actor['id'])
            : null;

        try {
            $result = $retryTask->execute(
                $validated['task_type'],
                $validated['task_id'],
                $request->session()->getId(),
            );
        } catch (\LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        match ($result['task_type']) {
            'kompen_respon' => ProcessKompenResponHubImport::dispatch($result['task_id']),
            'active_students' => ProcessKompenResponHubActiveStudentImport::dispatch($result['task_id']),
            'export' => GenerateKompenResponHubExport::dispatch($result['task_id']),
        };

        $activity->execute(
            'task.retry_requested',
            'task',
            "{$result['task_type']}:{$result['task_id']}",
            $admin,
            $request,
            period: $result['periode_semester'],
            reason: 'Pekerjaan gagal dijadwalkan ulang oleh admin.',
            metadata: ['task_type' => $result['task_type'], 'task_id' => $result['task_id']],
            subjectName: $result['subject_name'],
        );

        return back()->with('success', "{$result['subject_name']} dijadwalkan ulang. Pantau statusnya pada Diagnostik Pekerjaan.");
    }
}
