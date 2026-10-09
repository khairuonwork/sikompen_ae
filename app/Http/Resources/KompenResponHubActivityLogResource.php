<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KompenResponHubActivityLog */
class KompenResponHubActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'subject_type' => $this->subject_type,
            'subject_reference' => $this->subject_reference,
            'nim' => $this->nim,
            'subject_name' => $this->subject_name,
            'subject_label' => $this->subjectLabel(),
            'subject_context' => $this->subjectContext(),
            'periode_semester' => $this->periode_semester,
            'kelas' => $this->kelas,
            'actor_type' => $this->actor_type,
            'actor_name' => $this->actor_name,
            'actor_email' => $this->actor_email,
            'reason' => $this->reason,
            'before_state' => $this->before_state,
            'after_state' => $this->after_state,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }

    private function subjectLabel(): string
    {
        if (in_array($this->subject_type, ['student_progress', 'student_summary_override', 'detail_override', 'warning_letter'], true)) {
            $studentName = $this->subject_name ?? 'Mahasiswa tidak diketahui';

            return "Mahasiswa: {$studentName}";
        }

        if (in_array($this->subject_type, ['import', 'import_task'], true)) {
            return 'Workbook: '.($this->subject_name ?? $this->metadataFilename() ?? 'Tidak diketahui');
        }

        if ($this->subject_type === 'export') {
            return "Ekspor: {$this->exportResourceLabel()} ({$this->exportFormatLabel()})";
        }

        if ($this->subject_type === 'period_cutoff') {
            return 'Periode: '.($this->periode_semester ?? 'Tidak diketahui');
        }

        if ($this->subject_type === 'system_setting') {
            return 'Pengaturan: '.($this->subject_name ?? 'Sistem Sikompen');
        }

        return $this->subject_name ?? 'Sistem Sikompen';
    }

    private function subjectContext(): ?string
    {
        if (in_array($this->subject_type, ['student_progress', 'student_summary_override', 'detail_override', 'warning_letter'], true)) {
            return $this->joinContext([
                $this->nim === null ? null : "NIM {$this->nim}",
                $this->kelas === null ? null : "Kelas {$this->kelas}",
                $this->periode_semester,
            ]);
        }

        if ($this->subject_type === 'export') {
            return $this->exportFilterSummary();
        }

        if (in_array($this->subject_type, ['import', 'import_task', 'period_cutoff'], true)) {
            return $this->periode_semester;
        }

        return null;
    }

    private function exportResourceLabel(): string
    {
        return match ($this->metadataValue('resource')) {
            'students' => 'Kompen dan Respon',
            'details' => 'Detail Kompen',
            'warnings' => 'Surat Peringatan',
            default => 'Data Sikompen',
        };
    }

    private function exportFormatLabel(): string
    {
        $format = $this->metadataValue('format');

        return $format === null ? 'FILE' : strtoupper($format);
    }

    private function exportFilterSummary(): string
    {
        $filters = $this->metadata['filters'] ?? [];

        if (! is_array($filters)) {
            return 'Filter tidak tersedia';
        }

        $labels = [
            'periode_semester' => 'Periode',
            'tingkat' => 'Tingkat',
            'kelas' => 'Kelas',
            'nim' => 'NIM',
            'nama' => 'Nama',
            'search' => 'Pencarian',
            'mata_kuliah' => 'Mata kuliah',
            'nama_dosen' => 'Dosen',
        ];
        $segments = [];

        foreach ($labels as $filter => $label) {
            $value = $filters[$filter] ?? null;

            if (is_scalar($value) && filled((string) $value)) {
                $segments[] = "{$label}: {$value}";
            }
        }

        return $segments === [] ? 'Semua data' : implode(' · ', $segments);
    }

    private function metadataFilename(): ?string
    {
        return $this->metadataValue('original_filename');
    }

    private function metadataValue(string $key): ?string
    {
        $value = $this->metadata[$key] ?? null;

        return is_scalar($value) && filled((string) $value) ? (string) $value : null;
    }

    /** @param list<string|null> $segments */
    private function joinContext(array $segments): ?string
    {
        $context = array_values(array_filter($segments, static fn (?string $segment): bool => $segment !== null));

        return $context === [] ? null : implode(' · ', $context);
    }
}
