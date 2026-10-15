<?php

namespace App\Http\Resources;

use App\Models\KompenResponHubStudent;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class KompenResponHubOutboundStudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KompenResponHubStudent $student */
        $student = $this->resource;
        $kompenTotal = (float) $student->effective_total_kompensasi_jam;
        $kompenSelesai = (float) $student->effective_kompensasi_dikerjakan_jam;
        $responTotal = (float) $student->effective_total_responsi_jam;
        $responSelesai = (float) $student->effective_responsi_dikerjakan_jam;
        $lastChangedAt = $this->lastChangedAt($student);

        return [
            'schema_version' => 'v1',
            'identitas' => [
                'nim' => $student->nim,
                'nama' => $student->nama_mahasiswa,
            ],
            'akademik' => [
                'tingkat' => $student->tingkat,
                'kelas' => $student->kelas,
                'periode' => $student->periode_semester,
            ],
            'kompen' => [
                'total_jam' => $kompenTotal,
                'jam_selesai' => $kompenSelesai,
                'sisa_jam' => max(0, $kompenTotal - $kompenSelesai),
                'status' => $kompenTotal <= $kompenSelesai ? 'selesai' : 'berjalan',
            ],
            'respon' => [
                'total_jam' => $responTotal,
                'jam_selesai' => $responSelesai,
                'sisa_jam' => max(0, $responTotal - $responSelesai),
            ],
            'sinkronisasi' => [
                'record_version' => 'rec_'.Str::substr(hash('sha256', implode('|', [
                    $student->nim,
                    $student->periode_semester,
                    $lastChangedAt->toIso8601String(),
                    $kompenTotal,
                    $kompenSelesai,
                    $responTotal,
                    $responSelesai,
                ])), 0, 24),
                'updated_at' => $lastChangedAt->setTimezone('Asia/Jakarta')->toIso8601String(),
                'timezone' => 'Asia/Jakarta',
            ],
        ];
    }

    private function lastChangedAt(KompenResponHubStudent $student): CarbonInterface
    {
        $timestamps = array_filter([
            $student->updated_at,
            $student->progress?->updated_at,
            $student->summaryOverride?->updated_at,
        ], fn (mixed $value): bool => $value instanceof CarbonInterface);

        /** @var CarbonInterface $latest */
        $latest = collect($timestamps)->sortDesc()->first() ?? now();

        return $latest;
    }
}
