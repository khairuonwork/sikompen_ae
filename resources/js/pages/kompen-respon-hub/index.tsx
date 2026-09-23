import { Form, Head, Link } from '@inertiajs/react';
import {
    Download,
    FileText,
    FileSpreadsheet,
    ListFilter,
    Search,
    Settings,
    UploadCloud,
    LogOut,
    CheckCircle2,
    AlertCircle,
    ArrowLeft,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    PencilLine,
    ShieldAlert,
    Clock3,
    Save,
} from 'lucide-react';
import { type DragEvent, useEffect, useState } from 'react';
import {
    downloadUploadedWorkbook,
    downloadTemplate,
    store,
} from '@/actions/App/Http/Controllers/KompenResponHubImportController';
import { destroy as rollbackLatestImport } from '@/actions/App/Http/Controllers/KompenResponHubImportRollbackController';
import { show as importTaskStatus } from '@/actions/App/Http/Controllers/KompenResponHubImportTaskController';
import { destroy as logout } from '@/actions/App/Http/Controllers/AdminAuthenticationController';
import { settings as adminSettings } from '@/actions/App/Http/Controllers/KompenResponHubAdminSetupController';
import {
    details as downloadDetails,
    detailsPdf as downloadDetailsPdf,
    students as downloadStudents,
    studentsPdf as downloadStudentsPdf,
    warnings as downloadWarnings,
    warningsPdf as downloadWarningsPdf,
} from '@/actions/App/Http/Controllers/KompenResponHubDownloadController';
import {
    storeCutoff,
    storeDetailOverride,
    storeProgress,
    storeSummaryOverride,
    storeWarning,
    updateWarning,
} from '@/actions/App/Http/Controllers/KompenResponHubLifecycleController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { index as adminIndex } from '@/routes/admin/kompen-respon';
import { index as studentIndex } from '@/routes/student/kompen-respon';

type Student = {
    id: number;
    tingkat: number;
    nim: string;
    nama_mahasiswa: string;
    kelas: string;
    periode_semester: string;
    total_jam_terlambat: string;
    total_jam_sakit: string;
    total_jam_izin: string;
    total_jam_bolos: string;
    total_kompensasi_jam: string;
    total_responsi_jam: string;
    total_hutang_jam: string;
    kompensasi_dikerjakan_jam: string;
    sisa_hutang_jam: string;
    effective_total_kompensasi_jam: string;
    effective_total_responsi_jam: string;
    effective_total_hutang_jam: string;
    effective_kompensasi_dikerjakan_jam: string;
    effective_responsi_dikerjakan_jam: string;
    effective_sisa_hutang_jam: string;
    last_worked_at: string | null;
    has_summary_override: boolean;
    warning: Warning | null;
};

type Detail = {
    id: number;
    student_id: number;
    nim: string;
    nama_mahasiswa: string;
    kelas: string;
    mata_kuliah: string;
    nama_dosen: string;
    tanggal: string;
    jenis_pertemuan: string;
    presensi: string;
    menit_keterlambatan: number;
    keterangan: string | null;
    jam_kompensasi: string;
    jam_responsi: string;
    has_override: boolean;
};

type Warning = {
    id: number;
    student_id: number | null;
    nim: string;
    nama_mahasiswa: string;
    kelas: string;
    periode_semester: string;
    classification: 'temporary' | 'fixed';
    letter_status: 'not_created' | 'draft' | 'issued' | 'cancelled';
    resolution: 'outstanding' | 'completed' | 'needs_review';
    snapshot: { sisa_hutang_jam: number };
    reason: string | null;
    issued_at: string | null;
    cancelled_at: string | null;
};

type ActivityLog = {
    id: number;
    event_type: string;
    subject_type: string;
    nim: string | null;
    periode_semester: string | null;
    kelas: string | null;
    actor_type: string;
    actor_name: string | null;
    reason: string | null;
    occurred_at: string;
};

type Cutoff = {
    id: number;
    periode_semester: string;
    deadline_at: string;
    timezone: string;
};

type ImportAuditLog = {
    id: number;
    event_type: 'upload' | 'rollback';
    source_import_id: number | null;
    can_download_file: boolean;
    actor_name: string | null;
    actor_email: string | null;
    periode_semester: string;
    original_filename: string;
    class_count: number;
    student_count: number;
    detail_count: number;
    occurred_at: string;
};

type ImportTask = {
    id: number;
    original_filename: string;
    status: 'queued' | 'processing' | 'completed' | 'failed';
    progress: number;
    progress_message: string;
    error_message: string | null;
    queued_at: string | null;
    completed_at: string | null;
};

type Pagination<T> = {
    data: T[];
    links: { prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
};

type Filters = {
    search?: string;
    tingkat?: number;
    kelas?: string;
    periode_semester?: string;
    per_page?: number;
};

type FilterOptions = {
    tingkat: number[];
    kelas: string[];
    periode_semester: string[];
};

type KompenResponHubPageProps = {
    activeTab:
        'upload' | 'students' | 'details' | 'imports' | 'warnings' | 'activity';
    isAdmin: boolean;
    filters: Filters;
    filterOptions: FilterOptions;
    flash: { success: string | null; error: string | null };
    students: Pagination<Student> | null;
    details: Pagination<Detail> | null;
    imports: Pagination<ImportAuditLog> | null;
    warnings: Pagination<Warning> | null;
    temporaryCandidates: Pagination<Student> | null;
    activityLogs: Pagination<ActivityLog> | null;
    cutoffs: Cutoff[];
    activeImportTasks: ImportTask[];
    canRollbackLatestImport: boolean;
};

const adminTabs = [
    ['upload', 'Upload dokumen'],
    ['students', 'Kompen dan Respon'],
    ['details', 'Detail Kompen'],
    ['imports', 'Log upload'],
    ['warnings', 'Surat peringatan'],
    ['activity', 'Riwayat aktivitas'],
] as const;

const studentTabs = [
    ['students', 'Kompen dan Respon'],
    ['details', 'Detail Kompen'],
] as const;

function number(value: string): string {
    return new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function Pager<T>({ data }: { data: Pagination<T> }): React.JSX.Element {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#F0F3FA] bg-white/40 px-5 py-3.5 text-xs font-medium text-[#395886]/80 md:text-sm">
            <span>
                Halaman{' '}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.current_page}
                </span>{' '}
                dari{' '}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.last_page}
                </span>{' '}
                ·{' '}
                <span className="font-extrabold text-[#395886]">
                    {data.meta.total}
                </span>{' '}
                total data
            </span>
            <div className="flex gap-2">
                {data.links.prev ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="rounded-xl border-[#8AAEE0] bg-white font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <Link
                            href={data.links.prev}
                            className="flex items-center gap-1"
                        >
                            <ChevronLeft className="size-4" />
                            Sebelumnya
                        </Link>
                    </Button>
                ) : null}
                {data.links.next ? (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="rounded-xl border-[#8AAEE0] bg-white font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                    >
                        <Link
                            href={data.links.next}
                            className="flex items-center gap-1"
                        >
                            Berikutnya
                            <ChevronRight className="size-4" />
                        </Link>
                    </Button>
                ) : null}
            </div>
        </div>
    );
}

function StudentTable({
    data,
    isEditMode = false,
    onSelect,
}: {
    data: Pagination<Student>;
    isEditMode?: boolean;
    onSelect?: (student: Student) => void;
}): React.JSX.Element {
    const headings = [
        'Tingkat',
        'NIM',
        'Nama',
        'Kelas',
        'Periode',
        'T[j]',
        'S[j]',
        'I[j]',
        'B[j]',
        'Kompen[j]',
        'Responsi[j]',
        'Total[j]',
        'Komp. selesai[j]',
        'Resp. selesai[j]',
        'Sisa[j]',
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1150px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr
                                key={student.id}
                                className={cn(
                                    'transition-colors duration-150 hover:bg-[#B1C9EF]/10',
                                    isEditMode &&
                                        'cursor-pointer ring-inset hover:ring-1 hover:ring-[#628ECB]',
                                )}
                                onClick={() =>
                                    isEditMode && onSelect?.(student)
                                }
                            >
                                <td className="px-4 py-3.5 font-semibold text-[#395886]">
                                    {student.tingkat}
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {student.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {student.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">
                                    {student.kelas}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">
                                    {student.periode_semester}
                                </td>
                                {[
                                    student.total_jam_terlambat,
                                    student.total_jam_sakit,
                                    student.total_jam_izin,
                                    student.total_jam_bolos,
                                    student.effective_total_kompensasi_jam,
                                    student.effective_total_responsi_jam,
                                    student.effective_total_hutang_jam,
                                    student.effective_kompensasi_dikerjakan_jam,
                                    student.effective_responsi_dikerjakan_jam,
                                    student.effective_sisa_hutang_jam,
                                ].map((value, valueIndex) => (
                                    <td
                                        key={`${student.id}-${valueIndex}`}
                                        className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums"
                                    >
                                        {number(value)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function DetailTable({
    data,
    isEditMode = false,
    onSelect,
}: {
    data: Pagination<Detail>;
    isEditMode?: boolean;
    onSelect?: (detail: Detail) => void;
}): React.JSX.Element {
    const headings = [
        'Tanggal',
        'NIM',
        'Nama',
        'Kelas',
        'Mata Kuliah',
        'Dosen',
        'Pertemuan',
        'Presensi',
        'Terlambat',
        'Kompen[j]',
        'Responsi[j]',
        'Keterangan',
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((detail) => (
                            <tr
                                key={detail.id}
                                className={cn(
                                    'transition-colors duration-150 hover:bg-[#B1C9EF]/10',
                                    isEditMode &&
                                        'cursor-pointer ring-inset hover:ring-1 hover:ring-[#628ECB]',
                                )}
                                onClick={() => isEditMode && onSelect?.(detail)}
                            >
                                <td className="px-4 py-3.5 text-xs font-semibold whitespace-nowrap text-[#395886]">
                                    {detail.tanggal}
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {detail.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {detail.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">
                                    {detail.kelas}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]">
                                    {detail.mata_kuliah}
                                </td>
                                <td className="px-4 py-3.5 text-xs text-[#395886]/80">
                                    {detail.nama_dosen}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-medium text-[#628ECB]">
                                    {detail.jenis_pertemuan}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#395886]">
                                    {detail.presensi}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {detail.menit_keterlambatan}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {number(detail.jam_kompensasi)}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs text-[#395886] tabular-nums">
                                    {number(detail.jam_responsi)}
                                </td>
                                <td className="max-w-60 truncate px-4 py-3.5 text-xs text-[#395886]/70">
                                    {detail.keterangan ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function ImportAuditLogTable({
    data,
}: {
    data: Pagination<ImportAuditLog>;
}): React.JSX.Element {
    const headings = [
        'Aksi',
        'Waktu',
        'Admin Pelaksana',
        'Periode',
        'Nama File',
        'File',
        'Kelas',
        'Mahasiswa',
        'Detail',
    ];

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1130px] text-sm">
                    <thead className="border-b border-[#F0F3FA] bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th key={heading} className="px-4 py-3.5">
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((auditLog) => (
                            <tr
                                key={auditLog.id}
                                className="transition-colors duration-150 hover:bg-[#B1C9EF]/10"
                            >
                                <td className="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        className={cn(
                                            'rounded-full px-2.5 py-1 text-[10px] font-black tracking-wider uppercase',
                                            auditLog.event_type === 'upload'
                                                ? 'border border-emerald-200 bg-emerald-100 text-emerald-800'
                                                : 'border border-rose-200 bg-rose-100 text-rose-800',
                                        )}
                                    >
                                        {auditLog.event_type === 'upload'
                                            ? 'Upload'
                                            : 'Rollback · Dihapus'}
                                    </span>
                                </td>
                                <td className="px-4 py-3.5 text-xs whitespace-nowrap text-[#395886]">
                                    {new Intl.DateTimeFormat('id-ID', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }).format(new Date(auditLog.occurred_at))}
                                </td>
                                <td className="px-4 py-3.5">
                                    <p className="text-xs font-bold text-[#395886]">
                                        {auditLog.actor_name ?? '—'}
                                    </p>
                                    <p className="text-[11px] text-[#395886]/60">
                                        {auditLog.actor_email ?? '—'}
                                    </p>
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">
                                    {auditLog.periode_semester}
                                </td>
                                <td className="max-w-64 truncate px-4 py-3.5 font-mono text-xs font-semibold text-[#395886]">
                                    {auditLog.original_filename}
                                </td>
                                <td className="px-4 py-3.5">
                                    {auditLog.can_download_file &&
                                    auditLog.source_import_id !== null ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                                        >
                                            <a
                                                href={downloadUploadedWorkbook.url(
                                                    auditLog.source_import_id,
                                                )}
                                                className="flex items-center gap-1.5"
                                            >
                                                <Download className="size-3.5" />
                                                Unduh
                                            </a>
                                        </Button>
                                    ) : (
                                        <span className="text-xs text-[#395886]/40 italic">
                                            Tidak tersedia
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-[#395886] tabular-nums">
                                    {auditLog.class_count}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-[#395886] tabular-nums">
                                    {auditLog.student_count}
                                </td>
                                <td className="px-4 py-3.5 text-right font-mono text-xs font-bold text-[#395886] tabular-nums">
                                    {auditLog.detail_count}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function RollbackLatestImportButton({
    canRollbackLatestImport,
}: {
    canRollbackLatestImport: boolean;
}): React.JSX.Element {
    return (
        <Form
            {...rollbackLatestImport.form()}
            onBefore={() =>
                window.confirm(
                    'Hapus data dari unggahan terakhir? Data mahasiswa dan detail terkait tidak dapat dipulihkan.',
                )
            }
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={!canRollbackLatestImport || processing}
                    className="rounded-2xl border-rose-300 bg-rose-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition-all duration-300 hover:bg-rose-700 active:scale-95 disabled:opacity-50"
                >
                    <RotateCcw className="mr-2 size-4" />
                    {processing
                        ? 'Menghapus unggahan…'
                        : 'Hapus unggahan terakhir'}
                </Button>
            )}
        </Form>
    );
}

function ProgressBar({ value }: { value: number }): React.JSX.Element {
    const progress = Math.min(100, Math.max(0, value));

    return (
        <div
            className="h-2.5 overflow-hidden rounded-full bg-[#D5DEEF]/60 shadow-inner"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={progress}
            aria-label={`Progres impor ${progress}%`}
        >
            <div
                className="h-full rounded-full bg-gradient-to-r from-[#395886] to-[#628ECB] transition-[width] duration-300"
                style={{ width: `${progress}%` }}
            />
        </div>
    );
}

function UploadProgressPanel({
    progress,
}: {
    progress: number;
}): React.JSX.Element {
    return (
        <div className="grid gap-2.5 rounded-2xl border border-[#8AAEE0]/50 bg-[#B1C9EF]/20 p-4 backdrop-blur-md">
            <div className="flex items-center justify-between gap-3 text-xs">
                <span className="font-extrabold text-[#395886]">
                    Mengirim workbook ke server
                </span>
                <span className="font-mono font-bold text-[#395886] tabular-nums">
                    {progress}%
                </span>
            </div>
            <ProgressBar value={progress} />
            <p className="text-[11px] font-medium text-[#395886]/70">
                Jangan berpindah menu sampai pengiriman file selesai.
            </p>
        </div>
    );
}

function ImportProgressPanel({
    initialImportTasks,
}: {
    initialImportTasks: ImportTask[];
}): React.JSX.Element | null {
    const [importTasks, setImportTasks] = useState(initialImportTasks);
    const activeTaskIds = importTasks
        .filter(
            (importTask) =>
                importTask.status === 'queued' ||
                importTask.status === 'processing',
        )
        .map((importTask) => importTask.id);
    const activeTaskKey = activeTaskIds.join(',');

    useEffect(() => {
        setImportTasks(initialImportTasks);
    }, [initialImportTasks]);

    useEffect(() => {
        if (activeTaskIds.length === 0) {
            return;
        }

        let isMounted = true;

        const refreshProgress = async (): Promise<void> => {
            try {
                const updatedTasks = await Promise.all(
                    activeTaskIds.map(async (importTaskId) => {
                        const response = await fetch(
                            importTaskStatus.url(importTaskId),
                            {
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            },
                        );

                        if (!response.ok) {
                            throw new Error('Status impor tidak dapat dimuat.');
                        }

                        const payload: { data: ImportTask } =
                            await response.json();

                        return payload.data;
                    }),
                );

                if (!isMounted) {
                    return;
                }

                setImportTasks((currentTasks) =>
                    currentTasks.map(
                        (importTask) =>
                            updatedTasks.find(
                                (updatedTask) =>
                                    updatedTask.id === importTask.id,
                            ) ?? importTask,
                    ),
                );
            } catch {
                return;
            }
        };

        void refreshProgress();
        const interval = window.setInterval(() => {
            void refreshProgress();
        }, 1500);

        return () => {
            isMounted = false;
            window.clearInterval(interval);
        };
    }, [activeTaskKey]);

    if (importTasks.length === 0) {
        return null;
    }

    return (
        <section className="grid gap-3" aria-live="polite">
            {importTasks.map((importTask) => (
                <div
                    key={importTask.id}
                    className="animate-in fade-in grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl duration-300"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-extrabold text-[#395886]">
                                Memproses {importTask.original_filename}
                            </p>
                            <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                                {importTask.progress_message}
                            </p>
                        </div>
                        <span className="rounded-full border border-[#8AAEE0]/40 bg-[#B1C9EF]/30 px-3 py-1 font-mono text-sm font-bold text-[#395886] tabular-nums">
                            {importTask.progress}%
                        </span>
                    </div>
                    <ProgressBar value={importTask.progress} />
                    {importTask.error_message ? (
                        <p className="mt-1 text-xs font-bold text-rose-600">
                            {importTask.error_message}
                        </p>
                    ) : null}
                </div>
            ))}
        </section>
    );
}

function UploadPanel({
    onUploadRequestActivityChange,
}: {
    onUploadRequestActivityChange: (isActive: boolean) => void;
}): React.JSX.Element {
    return (
        <Card className="group mx-auto w-full max-w-3xl overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl transition-all duration-500 hover:shadow-[0_15px_35px_rgba(57,88,134,0.15)]">
            <CardHeader className="border-b border-[#F0F3FA] pb-6">
                <CardTitle className="text-xl font-black tracking-tight text-[#395886] md:text-2xl">
                    Upload Workbook
                </CardTitle>
                <CardDescription className="mt-1 text-xs leading-relaxed font-normal text-[#395886]/70 md:text-sm">
                    Gunakan template yang sama dengan Sikompen. Data pada
                    periode dan kelas di workbook akan diperbarui langsung.
                </CardDescription>
            </CardHeader>
            <Form
                {...store.form()}
                resetOnSuccess
                onStart={() => onUploadRequestActivityChange(true)}
                onFinish={() => onUploadRequestActivityChange(false)}
            >
                {({ errors, processing, progress }) => (
                    <>
                        <CardContent className="grid gap-5 pt-6">
                            {/* Input Uploader Name */}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="uploader-name"
                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                >
                                    Nama Admin yang Mengunggah
                                </Label>
                                <Input
                                    id="uploader-name"
                                    name="uploader_name"
                                    autoComplete="name"
                                    maxLength={100}
                                    onDragOver={preventDropIntoUploaderName}
                                    onDrop={preventDropIntoUploaderName}
                                    required
                                    className="rounded-2xl border-[#8AAEE0] bg-white/90 py-2.5 text-sm text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                    placeholder="Masukkan nama admin"
                                />
                                {errors.uploader_name ? (
                                    <p className="mt-0.5 text-xs font-bold text-rose-600">
                                        {errors.uploader_name}
                                    </p>
                                ) : null}
                            </div>

                            {/* Input File */}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="kompen-respon-workbook"
                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                >
                                    Workbook XLSX
                                </Label>
                                <Input
                                    id="kompen-respon-workbook"
                                    name="file"
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    required
                                    className="cursor-pointer rounded-2xl border-[#8AAEE0] bg-white/90 py-2 text-sm text-[#395886] shadow-2xs transition-all duration-300 file:mr-4 file:rounded-xl file:border-0 file:bg-[#395886] file:px-3 file:py-1 file:text-xs file:font-bold file:text-white hover:file:bg-[#1E293B]"
                                />
                                <p className="text-[11px] leading-tight font-medium text-[#395886]/60">
                                    Maksimum 20 MB. Periode pada filter akan
                                    muncul otomatis setelah data berhasil
                                    diimpor.
                                </p>
                                {errors.file ? (
                                    <p className="mt-0.5 text-xs font-bold text-rose-600">
                                        {errors.file}
                                    </p>
                                ) : null}
                            </div>

                            {processing ? (
                                <UploadProgressPanel
                                    progress={progress?.percentage ?? 0}
                                />
                            ) : null}
                        </CardContent>
                        <CardFooter className="justify-end border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 pt-6 pb-6">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="rounded-2xl bg-[#395886] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:shadow-xl active:scale-95"
                            >
                                <UploadCloud className="mr-2 size-4" />
                                {processing
                                    ? 'Mengirim workbook…'
                                    : 'Upload & impor'}
                            </Button>
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}

function preventDropIntoUploaderName(event: DragEvent<HTMLInputElement>): void {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'none';
}

function FilterPanel({
    activeTab,
    isAdmin,
    filters,
    filterOptions,
}: {
    activeTab: 'students' | 'details';
    isAdmin: boolean;
    filters: Filters;
    filterOptions: FilterOptions;
}): React.JSX.Element {
    const [tingkat, setTingkat] = useState(filters.tingkat?.toString() ?? '');
    const [kelas, setKelas] = useState(filters.kelas ?? '');
    const [periode, setPeriode] = useState(filters.periode_semester ?? '');
    const [perPage, setPerPage] = useState(
        filters.per_page?.toString() ?? '15',
    );
    const indexAction = isAdmin ? adminIndex : studentIndex;
    const spreadsheetDownloadAction =
        activeTab === 'students' ? downloadStudents : downloadDetails;
    const pdfDownloadAction =
        activeTab === 'students' ? downloadStudentsPdf : downloadDetailsPdf;
    const downloadQuery = {
        search: filters.search,
        tingkat: tingkat || undefined,
        kelas: kelas || undefined,
        periode_semester: periode,
    };
    const spreadsheetDownloadUrl = periode
        ? spreadsheetDownloadAction.url({
              query: {
                  ...downloadQuery,
              },
          })
        : null;
    const pdfDownloadUrl = periode
        ? pdfDownloadAction.url({
              query: {
                  ...downloadQuery,
              },
          })
        : null;

    return (
        <Form
            {...indexAction.form()}
            className="grid grid-cols-2 gap-3.5 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl lg:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(140px,auto))]"
        >
            <input name="tab" type="hidden" value={activeTab} />
            <input name="tingkat" type="hidden" value={tingkat} />
            <input name="kelas" type="hidden" value={kelas} />
            <input name="periode_semester" type="hidden" value={periode} />
            <input name="per_page" type="hidden" value={perPage} />

            {/* Search Input */}
            <Input
                name="search"
                placeholder="Cari nama atau NIM..."
                defaultValue={filters.search}
                className="col-span-2 rounded-2xl border-[#8AAEE0] bg-white/90 py-2.5 text-xs text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886] lg:col-span-1"
            />

            {/* Select Tingkat */}
            <Select
                value={tingkat || undefined}
                onValueChange={(value) =>
                    setTingkat(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-full rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                    <SelectValue placeholder="Semua tingkat" />
                </SelectTrigger>
                <SelectContent className="rounded-2xl text-xs font-semibold">
                    <SelectItem
                        value="all"
                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                    >
                        Semua tingkat
                    </SelectItem>
                    {filterOptions.tingkat.map((option) => (
                        <SelectItem
                            key={option}
                            value={option.toString()}
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            Tingkat {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Select Kelas */}
            <Select
                value={kelas || undefined}
                onValueChange={(value) =>
                    setKelas(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-full rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                    <SelectValue placeholder="Semua kelas" />
                </SelectTrigger>
                <SelectContent className="rounded-2xl text-xs font-semibold">
                    <SelectItem
                        value="all"
                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                    >
                        Semua kelas
                    </SelectItem>
                    {filterOptions.kelas.map((option) => (
                        <SelectItem
                            key={option}
                            value={option}
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Select Periode */}
            <Select
                value={periode || undefined}
                onValueChange={(value) =>
                    setPeriode(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="col-span-2 w-full rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white lg:col-span-1">
                    <SelectValue placeholder="Semua periode upload" />
                </SelectTrigger>
                <SelectContent className="rounded-2xl text-xs font-semibold">
                    <SelectItem
                        value="all"
                        className="cursor-pointer hover:bg-[#B1C9EF]/20"
                    >
                        Semua periode upload
                    </SelectItem>
                    {filterOptions.periode_semester.map((option) => (
                        <SelectItem
                            key={option}
                            value={option}
                            className="cursor-pointer hover:bg-[#B1C9EF]/20"
                        >
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Controls Row */}
            <div className="col-span-2 flex gap-2 lg:col-span-1">
                <Select value={perPage} onValueChange={setPerPage}>
                    <SelectTrigger className="w-24 rounded-2xl border-[#8AAEE0] bg-white py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#628ECB] hover:bg-[#628ECB] hover:text-white">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="rounded-2xl text-xs font-semibold">
                        {[15, 30, 50, 100].map((option) => (
                            <SelectItem
                                key={option}
                                value={option.toString()}
                                className="cursor-pointer hover:bg-[#B1C9EF]/20"
                            >
                                {option} data
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="submit"
                    className="grow rounded-2xl bg-[#395886] text-xs font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] active:scale-95"
                >
                    <Search className="mr-1.5 size-4" />
                    Terapkan
                </Button>
            </div>

            {/* Footer Row Actions */}
            <div className="col-span-full flex flex-wrap items-center justify-between gap-3 border-t border-[#F0F3FA] pt-2">
                <Link
                    href={indexAction.url({ query: { tab: activeTab } })}
                    className="text-xs font-bold text-[#395886] underline decoration-[#8AAEE0] underline-offset-4 transition-colors hover:text-[#628ECB]"
                >
                    Reset filter
                </Link>
                {spreadsheetDownloadUrl && pdfDownloadUrl ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <a
                                href={spreadsheetDownloadUrl}
                                className="flex items-center gap-1.5"
                            >
                                <FileSpreadsheet className="size-3.5" />
                                Download XLSX
                            </a>
                        </Button>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <a
                                href={pdfDownloadUrl}
                                className="flex items-center gap-1.5"
                            >
                                <FileText className="size-3.5" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                ) : (
                    <span className="text-xs font-semibold text-[#395886]/70 italic">
                        Pilih periode untuk mengunduh data.
                    </span>
                )}
            </div>
        </Form>
    );
}

function EditModeControl({
    enabled,
    onChange,
}: {
    enabled: boolean;
    onChange: (enabled: boolean) => void;
}): React.JSX.Element {
    return (
        <Button
            type="button"
            variant="outline"
            onClick={() => onChange(!enabled)}
            className={cn(
                'rounded-2xl border-[#8AAEE0] bg-white px-4 text-xs font-bold text-[#395886]',
                enabled &&
                    'border-[#395886] bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white',
            )}
        >
            <PencilLine className="mr-2 size-4" />
            {enabled ? 'Mode perbaikan aktif' : 'Mode perbaikan data'}
        </Button>
    );
}

function StudentCorrectionPanel({
    student,
}: {
    student: Student;
}): React.JSX.Element {
    return (
        <section className="grid gap-4 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm">
            <div>
                <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                    Mahasiswa dipilih
                </p>
                <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                    {student.nama_mahasiswa} · {student.nim}
                </h3>
                <p className="mt-1 text-xs text-[#395886]/70">
                    Simpan setiap koreksi dengan alasan agar jejak audit tetap
                    lengkap.
                </p>
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <Form
                    {...storeProgress.form(student.id)}
                    className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm font-bold text-[#395886]">
                                Progres pengerjaan
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    name="kompensasi_dikerjakan_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_kompensasi_dikerjakan_jam
                                    }
                                    placeholder="Jam Kompen"
                                />
                                <Input
                                    name="responsi_dikerjakan_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_responsi_dikerjakan_jam
                                    }
                                    placeholder="Jam Responsi"
                                />
                            </div>
                            <Input
                                name="last_worked_at"
                                type="datetime-local"
                                required
                                defaultValue={
                                    student.last_worked_at?.slice(0, 16) ?? ''
                                }
                            />
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan perubahan"
                            />
                            {errors.kompensasi_dikerjakan_jam ||
                            errors.last_worked_at ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.kompensasi_dikerjakan_jam ??
                                        errors.last_worked_at}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan progres
                            </Button>
                        </>
                    )}
                </Form>
                <Form
                    {...storeSummaryOverride.form(student.id)}
                    className="grid gap-3 rounded-2xl border border-[#D5DEEF] p-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <p className="text-sm font-bold text-[#395886]">
                                Koreksi total Kompen / Responsi
                            </p>
                            <div className="grid grid-cols-2 gap-3">
                                <Input
                                    name="total_kompensasi_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_total_kompensasi_jam
                                    }
                                    placeholder="Total Kompen"
                                />
                                <Input
                                    name="total_responsi_jam"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue={
                                        student.effective_total_responsi_jam
                                    }
                                    placeholder="Total Responsi"
                                />
                            </div>
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan koreksi"
                            />
                            {errors.total_kompensasi_jam ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.total_kompensasi_jam}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan koreksi
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </section>
    );
}

function DetailCorrectionPanel({
    detail,
}: {
    detail: Detail;
}): React.JSX.Element {
    return (
        <Form
            {...storeDetailOverride.form(detail.id)}
            className="grid gap-3 rounded-3xl border border-[#8AAEE0]/60 bg-white/90 p-5 shadow-sm md:grid-cols-2"
        >
            {({ errors, processing }) => (
                <>
                    <div className="md:col-span-2">
                        <p className="text-xs font-black tracking-[0.15em] text-[#628ECB] uppercase">
                            Detail dipilih
                        </p>
                        <h3 className="mt-1 text-base font-extrabold text-[#395886]">
                            {detail.nama_mahasiswa} · {detail.mata_kuliah}
                        </h3>
                    </div>
                    <Input
                        name="tanggal"
                        type="date"
                        required
                        defaultValue={detail.tanggal}
                    />
                    <Input
                        name="mata_kuliah"
                        required
                        maxLength={100}
                        defaultValue={detail.mata_kuliah}
                    />
                    <Input
                        name="nama_dosen"
                        required
                        maxLength={100}
                        defaultValue={detail.nama_dosen}
                    />
                    <Input
                        name="jenis_pertemuan"
                        required
                        maxLength={20}
                        defaultValue={detail.jenis_pertemuan}
                    />
                    <Input
                        name="presensi"
                        required
                        maxLength={20}
                        defaultValue={detail.presensi}
                    />
                    <Input
                        name="menit_keterlambatan"
                        type="number"
                        min="0"
                        defaultValue={detail.menit_keterlambatan}
                    />
                    <Input
                        name="jam_kompensasi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_kompensasi}
                    />
                    <Input
                        name="jam_responsi"
                        type="number"
                        min="0"
                        step="0.01"
                        defaultValue={detail.jam_responsi}
                    />
                    <Input
                        name="keterangan"
                        className="md:col-span-2"
                        maxLength={2000}
                        defaultValue={detail.keterangan ?? ''}
                        placeholder="Keterangan"
                    />
                    <Input
                        name="reason"
                        className="md:col-span-2"
                        required
                        minLength={5}
                        maxLength={1000}
                        placeholder="Alasan koreksi"
                    />
                    {errors.tanggal ? (
                        <p className="text-xs font-bold text-rose-600">
                            {errors.tanggal}
                        </p>
                    ) : null}
                    <Button
                        disabled={processing}
                        type="submit"
                        className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                    >
                        <Save className="mr-2 size-4" />
                        Simpan koreksi detail
                    </Button>
                </>
            )}
        </Form>
    );
}

function WarningStatusBadge({
    warning,
}: {
    warning: Warning;
}): React.JSX.Element {
    const label =
        warning.resolution === 'completed' &&
        warning.letter_status === 'not_created'
            ? 'Kompen selesai · SP tidak diterbitkan'
            : warning.resolution === 'completed'
              ? 'Kompen selesai setelah SP'
              : warning.letter_status === 'issued'
                ? 'SP-1 terbit'
                : warning.letter_status === 'draft'
                  ? 'Draft SP-1'
                  : warning.letter_status === 'cancelled'
                    ? 'SP dibatalkan'
                    : 'Belum dibuat';
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-[11px] font-extrabold',
                warning.resolution === 'completed'
                    ? 'bg-emerald-100 text-emerald-700'
                    : warning.letter_status === 'issued'
                      ? 'bg-rose-100 text-rose-700'
                      : warning.letter_status === 'cancelled'
                        ? 'bg-slate-100 text-slate-700'
                        : warning.letter_status === 'draft'
                          ? 'bg-amber-100 text-amber-700'
                          : 'bg-[#B1C9EF]/40 text-[#395886]',
            )}
        >
            {warning.classification === 'fixed' ? 'Fixed · ' : 'Temporary · '}
            {label}
        </span>
    );
}

function TemporaryCandidateTable({
    data,
    selectedStudentId,
    onSelect,
}: {
    data: Pagination<Student>;
    selectedStudentId: number | null;
    onSelect: (student: Student) => void;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="flex flex-col justify-between gap-2 border-b border-[#F0F3FA] px-5 py-4 sm:flex-row sm:items-center">
                <div>
                    <h2 className="text-base font-extrabold text-[#395886]">
                        Kandidat sementara
                    </h2>
                    <p className="mt-0.5 text-xs text-[#395886]/70">
                        Mahasiswa dengan sisa jam pada periode yang batas
                        waktunya belum lewat. Pilih satu untuk membuat draft
                        SP-1.
                    </p>
                </div>
                <span className="w-fit rounded-full bg-[#B1C9EF]/40 px-3 py-1 text-xs font-bold text-[#395886]">
                    Temporary
                </span>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Mahasiswa</th>
                            <th className="px-4 py-3">Kelas</th>
                            <th className="px-4 py-3">Periode</th>
                            <th className="px-4 py-3 text-right">Sisa[j]</th>
                            <th className="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr
                                key={student.id}
                                className={cn(
                                    'transition-colors hover:bg-[#B1C9EF]/10',
                                    selectedStudentId === student.id &&
                                        'bg-[#B1C9EF]/20',
                                )}
                            >
                                <td className="px-4 py-3">
                                    <p className="font-bold text-[#395886]">
                                        {student.nama_mahasiswa}
                                    </p>
                                    <p className="font-mono text-xs text-[#628ECB]">
                                        {student.nim}
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-xs font-semibold text-[#395886]">
                                    {student.kelas}
                                </td>
                                <td className="px-4 py-3 text-xs text-[#395886]/70">
                                    {student.periode_semester}
                                </td>
                                <td className="px-4 py-3 text-right font-mono text-xs font-bold text-[#395886]">
                                    {number(student.effective_sisa_hutang_jam)}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => onSelect(student)}
                                        className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                                    >
                                        {selectedStudentId === student.id
                                            ? 'Dipilih'
                                            : 'Pilih'}
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function WarningTable({
    data,
    onSelect,
}: {
    data: Pagination<Warning>;
    onSelect: (warning: Warning) => void;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[850px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Mahasiswa</th>
                            <th className="px-4 py-3">Kelas</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Sisa[j]</th>
                            <th className="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((warning) => (
                            <tr
                                key={warning.id}
                                onClick={() => onSelect(warning)}
                                className="cursor-pointer hover:bg-[#B1C9EF]/10"
                            >
                                <td className="px-4 py-3">
                                    <p className="font-bold text-[#395886]">
                                        {warning.nama_mahasiswa}
                                    </p>
                                    <p className="font-mono text-xs text-[#628ECB]">
                                        {warning.nim}
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-xs font-semibold">
                                    {warning.kelas}
                                </td>
                                <td className="px-4 py-3">
                                    <WarningStatusBadge warning={warning} />
                                </td>
                                <td className="px-4 py-3 text-right font-mono text-xs">
                                    {number(
                                        String(
                                            warning.snapshot.sisa_hutang_jam,
                                        ),
                                    )}
                                </td>
                                <td className="max-w-xs px-4 py-3 text-xs text-[#395886]/70">
                                    {warning.reason ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function WarningPanel({
    cutoffs,
    filterOptions,
    filters,
    warnings,
    temporaryCandidates,
    selectedWarning,
    onSelectWarning,
}: {
    cutoffs: Cutoff[];
    filterOptions: FilterOptions;
    filters: Filters;
    warnings: Pagination<Warning> | null;
    temporaryCandidates: Pagination<Student> | null;
    selectedWarning: Warning | null;
    onSelectWarning: (warning: Warning) => void;
}): React.JSX.Element {
    const [selectedStudent, setSelectedStudent] = useState<Student | null>(
        null,
    );
    const warningDownloadUrl = filters.periode_semester
        ? downloadWarnings.url({
              query: {
                  search: filters.search,
                  kelas: filters.kelas,
                  periode_semester: filters.periode_semester,
              },
          })
        : null;
    const warningPdfDownloadUrl = filters.periode_semester
        ? downloadWarningsPdf.url({
              query: {
                  search: filters.search,
                  kelas: filters.kelas,
                  periode_semester: filters.periode_semester,
              },
          })
        : null;

    return (
        <section className="grid gap-4">
            <Form
                {...adminIndex.form()}
                className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm md:grid-cols-[minmax(220px,1fr)_minmax(180px,auto)_minmax(180px,auto)_auto]"
            >
                <input name="tab" type="hidden" value="warnings" />
                <Input
                    name="search"
                    defaultValue={filters.search}
                    maxLength={100}
                    placeholder="Cari nama atau NIM"
                    className="rounded-xl border-[#8AAEE0] bg-white"
                />
                <select
                    name="kelas"
                    defaultValue={filters.kelas ?? ''}
                    className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm font-medium text-[#395886]"
                >
                    <option value="">Semua kelas</option>
                    {filterOptions.kelas.map((kelas) => (
                        <option key={kelas} value={kelas}>
                            {kelas}
                        </option>
                    ))}
                </select>
                <select
                    name="periode_semester"
                    defaultValue={filters.periode_semester ?? ''}
                    className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm font-medium text-[#395886]"
                >
                    <option value="">Semua periode</option>
                    {filterOptions.periode_semester.map((period) => (
                        <option key={period} value={period}>
                            {period}
                        </option>
                    ))}
                </select>
                <Button
                    type="submit"
                    className="rounded-xl bg-[#395886] text-xs font-bold"
                >
                    <Search className="mr-1.5 size-4" />
                    Terapkan
                </Button>
            </Form>
            <div className="grid gap-4 xl:grid-cols-2">
                <Form
                    {...storeCutoff.form()}
                    className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <h2 className="text-lg font-extrabold text-[#395886]">
                                    Batas waktu periode
                                </h2>
                                <p className="mt-1 text-xs text-[#395886]/70">
                                    Timezone operasional: Asia/Jakarta.
                                </p>
                            </div>
                            <select
                                name="periode_semester"
                                required
                                className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
                            >
                                <option value="">Pilih periode</option>
                                {filterOptions.periode_semester.map(
                                    (period) => (
                                        <option key={period} value={period}>
                                            {period}
                                        </option>
                                    ),
                                )}
                            </select>
                            <Input
                                name="deadline_at"
                                type="datetime-local"
                                required
                            />
                            {errors.deadline_at ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.deadline_at}
                                </p>
                            ) : null}
                            <Button
                                disabled={processing}
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Clock3 className="mr-2 size-4" />
                                Simpan batas waktu
                            </Button>
                            <div className="text-xs text-[#395886]/70">
                                {cutoffs.map((cutoff) => (
                                    <p key={cutoff.id}>
                                        {cutoff.periode_semester}:{' '}
                                        {new Date(
                                            cutoff.deadline_at,
                                        ).toLocaleString('id-ID', {
                                            timeZone: cutoff.timezone,
                                        })}
                                    </p>
                                ))}
                            </div>
                        </>
                    )}
                </Form>
                <Form
                    {...storeWarning.form()}
                    className="grid gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <h2 className="text-lg font-extrabold text-[#395886]">
                                    Tambah SP-1 manual
                                </h2>
                                <p className="mt-1 text-xs text-[#395886]/70">
                                    Pilih kandidat pada tabel di bawah. Sistem
                                    menyimpan snapshot sisa jam saat ini.
                                </p>
                            </div>
                            <input
                                name="student_id"
                                type="hidden"
                                value={selectedStudent?.id ?? ''}
                                required
                            />
                            <div className="rounded-xl border border-[#D5DEEF] bg-[#F0F3FA]/70 px-3 py-2.5 text-xs text-[#395886]">
                                {selectedStudent ? (
                                    <span>
                                        <strong>
                                            {selectedStudent.nama_mahasiswa}
                                        </strong>
                                        {' · '}
                                        {selectedStudent.nim}
                                        {' · sisa '}
                                        {number(
                                            selectedStudent.effective_sisa_hutang_jam,
                                        )}
                                        {' jam'}
                                    </span>
                                ) : (
                                    'Belum ada mahasiswa yang dipilih.'
                                )}
                            </div>
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                maxLength={1000}
                                placeholder="Alasan pembuatan draft SP-1"
                            />
                            {errors.student_id ? (
                                <p className="text-xs font-bold text-rose-600">
                                    {errors.student_id}
                                </p>
                            ) : null}
                            <Button
                                disabled={
                                    processing || selectedStudent === null
                                }
                                type="submit"
                                className="w-fit rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <ShieldAlert className="mr-2 size-4" />
                                Buat draft SP-1
                            </Button>
                        </>
                    )}
                </Form>
            </div>
            {temporaryCandidates?.data.length ? (
                <TemporaryCandidateTable
                    data={temporaryCandidates}
                    selectedStudentId={selectedStudent?.id ?? null}
                    onSelect={setSelectedStudent}
                />
            ) : (
                <div className="rounded-3xl border border-dashed border-[#8AAEE0] bg-white/80 p-6 text-center text-xs font-semibold text-[#395886]/70">
                    Tidak ada kandidat sementara. Tambahkan batas waktu untuk
                    periode yang masih berjalan atau periksa sisa jam mahasiswa.
                </div>
            )}
            <div className="flex flex-col justify-between gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-sm sm:flex-row sm:items-center">
                <div>
                    <h2 className="text-base font-extrabold text-[#395886]">
                        Arsip dan status SP-1
                    </h2>
                    <p className="mt-0.5 text-xs text-[#395886]/70">
                        Fixed dibuat saat batas waktu terlewati; draft dan
                        penerbitan SP-1 tetap dikendalikan admin.
                    </p>
                </div>
                {warningDownloadUrl && warningPdfDownloadUrl ? (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <a href={warningDownloadUrl}>
                                <FileSpreadsheet className="mr-1.5 size-3.5" />
                                XLSX
                            </a>
                        </Button>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <a href={warningPdfDownloadUrl}>
                                <FileText className="mr-1.5 size-3.5" />
                                PDF
                            </a>
                        </Button>
                    </div>
                ) : (
                    <p className="text-xs font-semibold text-[#395886]/60">
                        Pilih periode dengan filter untuk mengekspor arsip.
                    </p>
                )}
            </div>
            {warnings?.data.length ? (
                <WarningTable data={warnings} onSelect={onSelectWarning} />
            ) : (
                <EmptyTableState isAdmin />
            )}
            {selectedWarning ? (
                <Form
                    {...updateWarning.form(selectedWarning.id)}
                    className="grid gap-3 rounded-3xl border border-[#8AAEE0]/60 bg-white p-5 md:grid-cols-[1fr_1fr_auto]"
                >
                    {({ processing }) => (
                        <>
                            <select
                                name="letter_status"
                                defaultValue={selectedWarning.letter_status}
                                className="h-10 rounded-xl border border-[#8AAEE0] bg-white px-3 text-sm text-[#395886]"
                            >
                                <option value="draft">Draft SP-1</option>
                                <option value="issued">Terbitkan SP-1</option>
                                <option value="cancelled">Batalkan SP-1</option>
                            </select>
                            <Input
                                name="reason"
                                required
                                minLength={5}
                                defaultValue={selectedWarning.reason ?? ''}
                                placeholder="Alasan perubahan status"
                            />
                            <Button
                                disabled={processing}
                                type="submit"
                                className="rounded-xl bg-[#395886] text-xs font-bold"
                            >
                                <Save className="mr-2 size-4" />
                                Simpan SP
                            </Button>
                        </>
                    )}
                </Form>
            ) : null}
        </section>
    );
}

function ActivityLogTable({
    data,
}: {
    data: Pagination<ActivityLog>;
}): React.JSX.Element {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[750px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                        <tr>
                            <th className="px-4 py-3">Waktu</th>
                            <th className="px-4 py-3">Aktivitas</th>
                            <th className="px-4 py-3">Subjek</th>
                            <th className="px-4 py-3">Aktor</th>
                            <th className="px-4 py-3">Alasan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((log) => (
                            <tr key={log.id}>
                                <td className="px-4 py-3 text-xs">
                                    {new Date(log.occurred_at).toLocaleString(
                                        'id-ID',
                                        { timeZone: 'Asia/Jakarta' },
                                    )}
                                </td>
                                <td className="px-4 py-3 font-mono text-xs text-[#395886]">
                                    {log.event_type}
                                </td>
                                <td className="px-4 py-3 text-xs">
                                    {log.nim ?? log.subject_type}
                                </td>
                                <td className="px-4 py-3 text-xs">
                                    {log.actor_name ?? 'Sistem'}
                                </td>
                                <td className="max-w-xs px-4 py-3 text-xs text-[#395886]/70">
                                    {log.reason ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pager data={data} />
        </section>
    );
}

function EmptyTableState({ isAdmin }: { isAdmin: boolean }): React.JSX.Element {
    return (
        <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-12 text-center text-sm backdrop-blur-xl transition-all duration-300">
            <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl border border-[#8AAEE0]/50 bg-[#B1C9EF]/40 text-[#395886] shadow-inner">
                <FileSpreadsheet className="size-7" />
            </div>
            <p className="text-base font-extrabold text-[#395886]">
                {isAdmin ? 'Belum Ada Data yang Cocok' : 'Belum Ada Data'}
            </p>
            <p className="mx-auto mt-1 max-w-sm text-xs leading-relaxed font-medium text-[#395886]/70">
                {isAdmin
                    ? 'Unggah workbook XLSX melalui tab Upload dokumen atau sesuaikan filter yang digunakan.'
                    : 'Belum ada data yang sesuai dengan filter yang dipilih. Coba pilih periode lain atau atur ulang filter.'}
            </p>
        </div>
    );
}

export default function KompenResponHubIndex({
    activeTab,
    isAdmin,
    filters,
    filterOptions,
    flash,
    students,
    details,
    imports,
    warnings,
    temporaryCandidates,
    activityLogs,
    cutoffs,
    activeImportTasks,
    canRollbackLatestImport,
}: KompenResponHubPageProps): React.JSX.Element {
    const indexAction = isAdmin ? adminIndex : studentIndex;
    const tabs = isAdmin ? adminTabs : studentTabs;
    const [isUploadRequestActive, setIsUploadRequestActive] = useState(false);
    const [isEditMode, setIsEditMode] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<Student | null>(
        null,
    );
    const [selectedDetail, setSelectedDetail] = useState<Detail | null>(null);
    const [selectedWarning, setSelectedWarning] = useState<Warning | null>(
        null,
    );

    return (
        <>
            <Head title="Kompen Respon Hub" />

            {/* Main Wrapper Full Width (Tanpa batas hitam di tepi kiri/kanan) */}
            <main className="relative min-h-screen w-full overflow-x-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886] md:p-8">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -left-40 size-[36rem] rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute top-1/3 -right-40 size-[36rem] rounded-full bg-[#B1C9EF]/40 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-20 left-1/4 size-[32rem] rounded-full bg-[#628ECB]/20 blur-3xl" />

                {/* Inner Content Container */}
                <div className="relative z-10 mx-auto flex w-full max-w-[1600px] flex-col gap-6">
                    {/* Page Header */}
                    <header className="flex flex-col justify-between gap-4 border-b border-[#D5DEEF] pb-6 md:flex-row md:items-end">
                        <div className="flex items-start gap-4">
                            <div className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                                <FileSpreadsheet className="size-7" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                    {isAdmin
                                        ? 'System Administration Portal'
                                        : 'Akses Mahasiswa • Read Only'}
                                </p>
                                <h1 className="mt-1 text-3xl font-black tracking-tight text-[#395886] md:text-4xl">
                                    Kompen Respon Hub
                                </h1>
                                <p className="mt-1 max-w-2xl text-xs leading-relaxed font-medium text-[#395886]/70 md:text-sm">
                                    {isAdmin
                                        ? 'Impor workbook Sikompen dan kelola ringkasan Kompen/Respon maupun detail kehadiran.'
                                        : 'Lihat data Kompen/Respon dan Detail Kompen, lalu unduh hasil sesuai periode yang dipilih.'}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled
                                className="rounded-2xl border-[#8AAEE0] bg-white/80 px-4 py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 disabled:opacity-100"
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            {isAdmin ? (
                                <>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-2xl border-[#8AAEE0] bg-white/80 px-4 py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white active:scale-95"
                                    >
                                        <Link
                                            href={adminSettings.url()}
                                            className="flex items-center gap-1.5"
                                        >
                                            <Settings className="size-4" />
                                            Pengaturan
                                        </Link>
                                    </Button>
                                    <a
                                        className="inline-flex h-9 items-center justify-center gap-1.5 rounded-2xl border border-[#8AAEE0] bg-white/80 px-4 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:bg-[#395886] hover:text-white active:scale-95"
                                        href={downloadTemplate.url()}
                                    >
                                        <Download className="size-4" />
                                        Download Template
                                    </a>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-2xl border-[#8AAEE0] bg-white/80 px-4 py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-rose-600 hover:bg-rose-600 hover:text-white active:scale-95"
                                    >
                                        <Link
                                            href={logout.url()}
                                            method="post"
                                            as="button"
                                            className="flex items-center gap-1.5"
                                        >
                                            <LogOut className="size-4" />
                                            Keluar
                                        </Link>
                                    </Button>
                                </>
                            ) : null}
                        </div>
                    </header>

                    {/* Alerts */}
                    {flash.success && (
                        <Alert className="animate-in fade-in slide-in-from-top-2 rounded-2xl border border-emerald-200 bg-emerald-50/90 text-emerald-900 shadow-sm backdrop-blur-md duration-300">
                            <CheckCircle2 className="size-5 animate-bounce text-emerald-600" />
                            <AlertTitle className="text-base font-bold text-emerald-950">
                                Tindakan Berhasil Disimpan
                            </AlertTitle>
                            <AlertDescription className="mt-0.5 text-xs text-emerald-700">
                                {flash.success}
                            </AlertDescription>
                        </Alert>
                    )}

                    {flash.error && (
                        <Alert
                            variant="destructive"
                            className="animate-in fade-in slide-in-from-top-2 rounded-2xl border border-rose-200 bg-rose-50/90 text-rose-900 shadow-sm backdrop-blur-md duration-300"
                        >
                            <AlertCircle className="size-5 animate-pulse text-rose-600" />
                            <AlertTitle className="text-base font-bold text-rose-950">
                                Tindakan Tidak Dapat Dijalankan
                            </AlertTitle>
                            <AlertDescription className="mt-0.5 text-xs text-rose-700">
                                {flash.error}
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Import Tasks Monitor */}
                    {isAdmin ? (
                        <ImportProgressPanel
                            initialImportTasks={activeImportTasks}
                        />
                    ) : null}

                    {/* Navigation Tabs */}
                    <nav
                        className="flex flex-wrap gap-2 border-b border-[#D5DEEF] pb-1"
                        aria-label={isAdmin ? 'Menu admin' : 'Menu mahasiswa'}
                    >
                        {tabs.map(([tab, label]) => (
                            <Link
                                key={tab}
                                href={indexAction.url({
                                    query:
                                        tab === 'upload' ||
                                        tab === 'imports' ||
                                        tab === 'warnings' ||
                                        tab === 'activity'
                                            ? { tab }
                                            : { ...filters, tab },
                                })}
                                onClick={(event) => {
                                    if (isUploadRequestActive) {
                                        event.preventDefault();
                                    }
                                }}
                                aria-disabled={isUploadRequestActive}
                                className={cn(
                                    'flex items-center gap-2 rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition-all duration-300',
                                    activeTab === tab
                                        ? 'border-[#395886] bg-white/70 text-[#395886] shadow-2xs'
                                        : 'border-transparent text-[#395886]/60 hover:bg-white/40 hover:text-[#395886]',
                                    isUploadRequestActive &&
                                        'pointer-events-none cursor-not-allowed opacity-50',
                                )}
                            >
                                {tab !== 'upload' ? (
                                    <ListFilter className="size-4" />
                                ) : null}
                                {label}
                            </Link>
                        ))}
                    </nav>

                    {isUploadRequestActive ? (
                        <p className="-mt-3 text-xs font-medium text-[#395886]/70">
                            Tunggu sampai file selesai dikirim sebelum berpindah
                            menu. Setelah itu impor akan berjalan di latar
                            belakang.
                        </p>
                    ) : null}

                    {/* Main Views */}
                    {activeTab === 'upload' ? (
                        <UploadPanel
                            onUploadRequestActivityChange={
                                setIsUploadRequestActive
                            }
                        />
                    ) : null}

                    {activeTab === 'imports' ? (
                        <section className="grid gap-4">
                            <div className="flex flex-col justify-between gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl sm:flex-row sm:items-center">
                                <div>
                                    <h2 className="text-lg font-extrabold text-[#395886]">
                                        Audit Impor
                                    </h2>
                                    <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                                        Riwayat upload dan penghapusan unggahan
                                        terakhir.
                                    </p>
                                </div>
                                <RollbackLatestImportButton
                                    canRollbackLatestImport={
                                        canRollbackLatestImport
                                    }
                                />
                            </div>
                            {imports?.data.length ? (
                                <ImportAuditLogTable data={imports} />
                            ) : (
                                <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-10 text-center text-xs font-bold text-[#395886]/70 backdrop-blur-xl">
                                    Belum ada riwayat upload atau rollback.
                                </div>
                            )}
                        </section>
                    ) : null}

                    {activeTab === 'warnings' && isAdmin ? (
                        <WarningPanel
                            cutoffs={cutoffs}
                            filterOptions={filterOptions}
                            filters={filters}
                            warnings={warnings}
                            temporaryCandidates={temporaryCandidates}
                            selectedWarning={selectedWarning}
                            onSelectWarning={setSelectedWarning}
                        />
                    ) : null}

                    {activeTab === 'activity' && isAdmin ? (
                        activityLogs?.data.length ? (
                            <ActivityLogTable data={activityLogs} />
                        ) : (
                            <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-10 text-center text-xs font-bold text-[#395886]/70">
                                Belum ada aktivitas yang tercatat.
                            </div>
                        )
                    ) : null}

                    {activeTab === 'students' || activeTab === 'details' ? (
                        <section className="flex flex-col gap-4">
                            {isAdmin ? (
                                <div className="flex justify-end">
                                    <EditModeControl
                                        enabled={isEditMode}
                                        onChange={setIsEditMode}
                                    />
                                </div>
                            ) : null}
                            <FilterPanel
                                key={activeTab}
                                activeTab={activeTab}
                                isAdmin={isAdmin}
                                filters={filters}
                                filterOptions={filterOptions}
                            />
                            {(activeTab === 'students' ? students : details)
                                ?.data.length ? (
                                activeTab === 'students' && students ? (
                                    <StudentTable
                                        data={students}
                                        isEditMode={isAdmin && isEditMode}
                                        onSelect={setSelectedStudent}
                                    />
                                ) : details ? (
                                    <DetailTable
                                        data={details}
                                        isEditMode={isAdmin && isEditMode}
                                        onSelect={setSelectedDetail}
                                    />
                                ) : null
                            ) : (
                                <EmptyTableState isAdmin={isAdmin} />
                            )}
                            {isAdmin &&
                            isEditMode &&
                            activeTab === 'students' &&
                            selectedStudent ? (
                                <StudentCorrectionPanel
                                    student={selectedStudent}
                                />
                            ) : null}
                            {isAdmin &&
                            isEditMode &&
                            activeTab === 'details' &&
                            selectedDetail ? (
                                <DetailCorrectionPanel
                                    detail={selectedDetail}
                                />
                            ) : null}
                        </section>
                    ) : null}
                </div>
            </main>
        </>
    );
}
