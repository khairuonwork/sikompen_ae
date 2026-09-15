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
    ChevronLeft,
    ChevronRight,
    RotateCcw
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
} from '@/actions/App/Http/Controllers/KompenResponHubDownloadController';
import { student as studentApi } from '@/actions/App/Http/Controllers/KompenResponHubController';
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
    activeTab: 'upload' | 'students' | 'details' | 'imports';
    isAdmin: boolean;
    filters: Filters;
    filterOptions: FilterOptions;
    flash: { success: string | null; error: string | null };
    students: Pagination<Student> | null;
    details: Pagination<Detail> | null;
    imports: Pagination<ImportAuditLog> | null;
    activeImportTasks: ImportTask[];
    canRollbackLatestImport: boolean;
};

const adminTabs = [
    ['upload', 'Upload dokumen'],
    ['students', 'Kompen dan Respon'],
    ['details', 'Detail Kompen'],
    ['imports', 'Log upload'],
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
        <div className="text-[#395886]/80 flex flex-wrap items-center justify-between gap-3 border-t border-[#F0F3FA] bg-white/40 px-5 py-3.5 text-xs md:text-sm font-medium">
            <span>
                Halaman <span className="font-extrabold text-[#395886]">{data.meta.current_page}</span> dari{' '}
                <span className="font-extrabold text-[#395886]">{data.meta.last_page}</span> ·{' '}
                <span className="font-extrabold text-[#395886]">{data.meta.total}</span> total data
            </span>
            <div className="flex gap-2">
                {data.links.prev ? (
                    <Button asChild size="sm" variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white hover:bg-[#395886] hover:text-white hover:border-[#395886] rounded-xl font-bold transition-all duration-300 shadow-2xs">
                        <Link href={data.links.prev} className="flex items-center gap-1">
                            <ChevronLeft className="size-4" />
                            Sebelumnya
                        </Link>
                    </Button>
                ) : null}
                {data.links.next ? (
                    <Button asChild size="sm" variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white hover:bg-[#395886] hover:text-white hover:border-[#395886] rounded-xl font-bold transition-all duration-300 shadow-2xs">
                        <Link href={data.links.next} className="flex items-center gap-1">
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
}: {
    data: Pagination<Student>;
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
        'Dikerjakan[j]',
        'Sisa[j]',
        'API',
    ];

    return (
        <section className="bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1150px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-[#395886] text-left text-[10px] font-black tracking-[0.15em] uppercase border-b border-[#F0F3FA]">
                        <tr>
                            {headings.map((heading) => (
                                <th
                                    key={heading}
                                    className="px-4 py-3.5"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((student) => (
                            <tr key={student.id} className="hover:bg-[#B1C9EF]/10 transition-colors duration-150">
                                <td className="px-4 py-3.5 font-semibold text-[#395886]">{student.tingkat}</td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {student.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {student.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">{student.kelas}</td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#628ECB]">
                                    {student.periode_semester}
                                </td>
                                {[
                                    student.total_jam_terlambat,
                                    student.total_jam_sakit,
                                    student.total_jam_izin,
                                    student.total_jam_bolos,
                                    student.total_kompensasi_jam,
                                    student.total_responsi_jam,
                                    student.total_hutang_jam,
                                    student.kompensasi_dikerjakan_jam,
                                    student.sisa_hutang_jam,
                                ].map((value, valueIndex) => (
                                    <td
                                        key={`${student.id}-${valueIndex}`}
                                        className="px-4 py-3.5 text-right tabular-nums font-mono text-xs text-[#395886]"
                                    >
                                        {number(value)}
                                    </td>
                                ))}
                                <td className="px-4 py-3.5">
                                    <a
                                        className="text-[#628ECB] hover:text-[#395886] font-bold text-xs underline decoration-[#8AAEE0] underline-offset-4 transition-colors"
                                        href={studentApi.url(student.id)}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Lengkap
                                    </a>
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

function DetailTable({
    data,
}: {
    data: Pagination<Detail>;
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
        <section className="bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-[#395886] text-left text-[10px] font-black tracking-[0.15em] uppercase border-b border-[#F0F3FA]">
                        <tr>
                            {headings.map((heading) => (
                                <th
                                    key={heading}
                                    className="px-4 py-3.5"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((detail) => (
                            <tr key={detail.id} className="hover:bg-[#B1C9EF]/10 transition-colors duration-150">
                                <td className="px-4 py-3.5 whitespace-nowrap text-xs font-semibold text-[#395886]">
                                    {detail.tanggal}
                                </td>
                                <td className="px-4 py-3.5 font-mono text-xs font-bold text-[#395886]">
                                    {detail.nim}
                                </td>
                                <td className="px-4 py-3.5 font-bold text-[#395886]">
                                    {detail.nama_mahasiswa}
                                </td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]/80">{detail.kelas}</td>
                                <td className="px-4 py-3.5 font-medium text-[#395886]">
                                    {detail.mata_kuliah}
                                </td>
                                <td className="px-4 py-3.5 text-xs text-[#395886]/80">
                                    {detail.nama_dosen}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-medium text-[#628ECB]">
                                    {detail.jenis_pertemuan}
                                </td>
                                <td className="px-4 py-3.5 text-xs font-semibold text-[#395886]">{detail.presensi}</td>
                                <td className="px-4 py-3.5 text-right tabular-nums font-mono text-xs text-[#395886]">
                                    {detail.menit_keterlambatan}
                                </td>
                                <td className="px-4 py-3.5 text-right tabular-nums font-mono text-xs text-[#395886]">
                                    {number(detail.jam_kompensasi)}
                                </td>
                                <td className="px-4 py-3.5 text-right tabular-nums font-mono text-xs text-[#395886]">
                                    {number(detail.jam_responsi)}
                                </td>
                                <td className="max-w-60 px-4 py-3.5 text-xs text-[#395886]/70 truncate">
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
        <section className="bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden backdrop-blur-xl transition-all duration-300">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1130px] text-sm">
                    <thead className="bg-[#B1C9EF]/20 text-[#395886] text-left text-[10px] font-black tracking-[0.15em] uppercase border-b border-[#F0F3FA]">
                        <tr>
                            {headings.map((heading) => (
                                <th
                                    key={heading}
                                    className="px-4 py-3.5"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-[#F0F3FA]">
                        {data.data.map((auditLog) => (
                            <tr
                                key={auditLog.id}
                                className="hover:bg-[#B1C9EF]/10 transition-colors duration-150"
                            >
                                <td className="px-4 py-3.5 whitespace-nowrap">
                                    <span className={cn(
                                        "px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider",
                                        auditLog.event_type === 'upload' 
                                            ? "bg-emerald-100 text-emerald-800 border border-emerald-200"
                                            : "bg-rose-100 text-rose-800 border border-rose-200"
                                    )}>
                                        {auditLog.event_type === 'upload' ? 'Upload' : 'Rollback · Dihapus'}
                                    </span>
                                </td>
                                <td className="px-4 py-3.5 whitespace-nowrap text-xs text-[#395886]">
                                    {new Intl.DateTimeFormat('id-ID', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }).format(new Date(auditLog.occurred_at))}
                                </td>
                                <td className="px-4 py-3.5">
                                    <p className="font-bold text-xs text-[#395886]">
                                        {auditLog.actor_name ?? '—'}
                                    </p>
                                    <p className="text-[#395886]/60 text-[11px]">
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
                                        <Button asChild size="sm" variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white hover:bg-[#395886] hover:text-white hover:border-[#395886] rounded-xl font-bold text-xs transition-all duration-300 shadow-2xs">
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
                                        <span className="text-[#395886]/40 text-xs italic">
                                            Tidak tersedia
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3.5 text-right tabular-nums font-mono text-xs font-bold text-[#395886]">
                                    {auditLog.class_count}
                                </td>
                                <td className="px-4 py-3.5 text-right tabular-nums font-mono text-xs font-bold text-[#395886]">
                                    {auditLog.student_count}
                                </td>
                                <td className="px-4 py-3.5 text-right tabular-nums font-mono text-xs font-bold text-[#395886]">
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
                    className="border-rose-300 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-bold text-xs px-5 py-2.5 transition-all duration-300 shadow-sm active:scale-95 disabled:opacity-50"
                >
                    <RotateCcw className="size-4 mr-2" />
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
            className="bg-[#D5DEEF]/60 h-2.5 overflow-hidden rounded-full shadow-inner"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={progress}
            aria-label={`Progres impor ${progress}%`}
        >
            <div
                className="bg-gradient-to-r from-[#395886] to-[#628ECB] h-full rounded-full transition-[width] duration-300"
                style={{ width: `${progress}%` }}
            />
        </div>
    );
}

function UploadProgressPanel({ progress }: { progress: number }): React.JSX.Element {
    return (
        <div className="bg-[#B1C9EF]/20 border border-[#8AAEE0]/50 grid gap-2.5 rounded-2xl p-4 backdrop-blur-md">
            <div className="flex items-center justify-between gap-3 text-xs">
                <span className="font-extrabold text-[#395886]">Mengirim workbook ke server</span>
                <span className="text-[#395886] font-mono font-bold tabular-nums">
                    {progress}%
                </span>
            </div>
            <ProgressBar value={progress} />
            <p className="text-[#395886]/70 text-[11px] font-medium">
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
                    className="bg-white/80 border border-white/80 grid gap-3 rounded-3xl p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl animate-in fade-in duration-300"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-extrabold text-[#395886]">
                                Memproses {importTask.original_filename}
                            </p>
                            <p className="text-[#395886]/70 text-xs mt-0.5 font-medium">
                                {importTask.progress_message}
                            </p>
                        </div>
                        <span className="text-[#395886] text-sm font-mono font-bold tabular-nums bg-[#B1C9EF]/30 px-3 py-1 rounded-full border border-[#8AAEE0]/40">
                            {importTask.progress}%
                        </span>
                    </div>
                    <ProgressBar value={importTask.progress} />
                    {importTask.error_message ? (
                        <p className="text-rose-600 text-xs font-bold mt-1">
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
        <Card className="group mx-auto w-full max-w-3xl bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_15px_35px_rgba(57,88,134,0.15)] transition-all duration-500 overflow-hidden backdrop-blur-xl">
            <CardHeader className="border-b border-[#F0F3FA] pb-6">
                <CardTitle className="text-xl md:text-2xl font-black text-[#395886] tracking-tight">
                    Upload Workbook
                </CardTitle>
                <CardDescription className="text-[#395886]/70 text-xs md:text-sm leading-relaxed mt-1 font-normal">
                    Gunakan template yang sama dengan Sikompen. Data pada periode dan kelas di workbook akan diperbarui langsung.
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
                                <Label htmlFor="uploader-name" className="text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
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
                                    className="bg-white/90 border-[#8AAEE0] text-[#395886] rounded-2xl focus-visible:ring-2 focus-visible:ring-[#395886] focus-visible:border-transparent transition-all duration-300 placeholder:text-[#395886]/40 text-sm py-2.5 shadow-2xs"
                                    placeholder="Masukkan nama admin"
                                />
                                {errors.uploader_name ? (
                                    <p className="text-rose-600 text-xs font-bold mt-0.5">
                                        {errors.uploader_name}
                                    </p>
                                ) : null}
                            </div>

                            {/* Input File */}
                            <div className="grid gap-2">
                                <Label htmlFor="kompen-respon-workbook" className="text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
                                    Workbook XLSX
                                </Label>
                                <Input
                                    id="kompen-respon-workbook"
                                    name="file"
                                    type="file"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    required
                                    className="bg-white/90 border-[#8AAEE0] text-[#395886] rounded-2xl file:mr-4 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#395886] file:text-white hover:file:bg-[#1E293B] cursor-pointer text-sm py-2 shadow-2xs transition-all duration-300"
                                />
                                <p className="text-[#395886]/60 text-[11px] font-medium leading-tight">
                                    Maksimum 20 MB. Periode pada filter akan muncul otomatis setelah data berhasil diimpor.
                                </p>
                                {errors.file ? (
                                    <p className="text-rose-600 text-xs font-bold mt-0.5">
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
                        <CardFooter className="justify-end border-t border-[#F0F3FA] pt-6 pb-6 px-6 bg-[#F0F3FA]/40">
                            <Button 
                                type="submit" 
                                disabled={processing}
                                className="bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl shadow-md hover:shadow-xl active:scale-95 transition-all duration-300 font-bold text-xs px-6 py-2.5"
                            >
                                <UploadCloud className="size-4 mr-2" />
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

function preventDropIntoUploaderName(
    event: DragEvent<HTMLInputElement>,
): void {
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
            className="bg-white/80 border border-white/80 grid gap-3.5 rounded-3xl p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl lg:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(140px,auto))]"
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
                className="bg-white/90 border-[#8AAEE0] text-[#395886] rounded-2xl focus-visible:ring-2 focus-visible:ring-[#395886] focus-visible:border-transparent transition-all duration-300 placeholder:text-[#395886]/40 text-xs py-2.5 shadow-2xs"
            />

            {/* Select Tingkat */}
            <Select
                value={tingkat || undefined}
                onValueChange={(value) =>
                    setTingkat(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-full bg-white/90 border-[#8AAEE0] text-[#395886] hover:bg-[#628ECB] hover:text-white hover:border-[#628ECB] rounded-2xl text-xs py-2.5 shadow-2xs font-bold transition-all duration-300">
                    <SelectValue placeholder="Semua tingkat" />
                </SelectTrigger>
                <SelectContent className="bg-white border-[#8AAEE0] rounded-2xl text-xs font-semibold text-[#395886]">
                    <SelectItem value="all" className="hover:bg-[#B1C9EF]/20 cursor-pointer">Semua tingkat</SelectItem>
                    {filterOptions.tingkat.map((option) => (
                        <SelectItem key={option} value={option.toString()} className="hover:bg-[#B1C9EF]/20 cursor-pointer">
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
                <SelectTrigger className="w-full bg-white/90 border-[#8AAEE0] text-[#395886] hover:bg-[#628ECB] hover:text-white hover:border-[#628ECB] rounded-2xl text-xs py-2.5 shadow-2xs font-bold transition-all duration-300">
                    <SelectValue placeholder="Semua kelas" />
                </SelectTrigger>
                <SelectContent className="bg-white border-[#8AAEE0] rounded-2xl text-xs font-semibold text-[#395886]">
                    <SelectItem value="all" className="hover:bg-[#B1C9EF]/20 cursor-pointer">Semua kelas</SelectItem>
                    {filterOptions.kelas.map((option) => (
                        <SelectItem key={option} value={option} className="hover:bg-[#B1C9EF]/20 cursor-pointer">
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
                <SelectTrigger className="w-full bg-white/90 border-[#8AAEE0] text-[#395886] hover:bg-[#628ECB] hover:text-white hover:border-[#628ECB] rounded-2xl text-xs py-2.5 shadow-2xs font-bold transition-all duration-300">
                    <SelectValue placeholder="Semua periode upload" />
                </SelectTrigger>
                <SelectContent className="bg-white border-[#8AAEE0] rounded-2xl text-xs font-semibold text-[#395886]">
                    <SelectItem value="all" className="hover:bg-[#B1C9EF]/20 cursor-pointer">Semua periode upload</SelectItem>
                    {filterOptions.periode_semester.map((option) => (
                        <SelectItem key={option} value={option} className="hover:bg-[#B1C9EF]/20 cursor-pointer">
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {/* Controls Row */}
            <div className="flex gap-2">
                <Select value={perPage} onValueChange={setPerPage}>
                    <SelectTrigger className="w-24 bg-white/90 border-[#8AAEE0] text-[#395886] hover:bg-[#628ECB] hover:text-white hover:border-[#628ECB] rounded-2xl text-xs py-2.5 shadow-2xs font-bold transition-all duration-300">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent className="bg-white border-[#8AAEE0] rounded-2xl text-xs font-semibold text-[#395886]">
                        {[15, 30, 50, 100].map((option) => (
                            <SelectItem key={option} value={option.toString()} className="hover:bg-[#B1C9EF]/20 cursor-pointer">
                                {option} data
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="submit" className="grow bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl font-bold text-xs shadow-md transition-all duration-300 active:scale-95">
                    <Search className="size-4 mr-1.5" />
                    Terapkan
                </Button>
            </div>

            {/* Footer Row Actions */}
            <div className="col-span-full flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-[#F0F3FA]">
                <Link
                    href={indexAction.url({ query: { tab: activeTab } })}
                    className="text-[#395886] hover:text-[#628ECB] text-xs font-bold underline decoration-[#8AAEE0] underline-offset-4 transition-colors"
                >
                    Reset filter
                </Link>
                {spreadsheetDownloadUrl && pdfDownloadUrl ? (
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white hover:bg-[#395886] hover:text-white hover:border-[#395886] rounded-xl font-bold text-xs transition-all duration-300 shadow-2xs">
                            <a href={spreadsheetDownloadUrl} className="flex items-center gap-1.5">
                                <FileSpreadsheet className="size-3.5" />
                                Download XLSX
                            </a>
                        </Button>
                        <Button asChild size="sm" variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white hover:bg-[#395886] hover:text-white hover:border-[#395886] rounded-xl font-bold text-xs transition-all duration-300 shadow-2xs">
                            <a href={pdfDownloadUrl} className="flex items-center gap-1.5">
                                <FileText className="size-3.5" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                ) : (
                    <span className="text-[#395886]/70 text-xs italic font-semibold">
                        Pilih periode untuk mengunduh data.
                    </span>
                )}
            </div>
        </Form>
    );
}

function EmptyTableState(): React.JSX.Element {
    return (
        <div className="bg-white/80 border-2 border-dashed border-[#8AAEE0] rounded-3xl p-12 text-center text-sm backdrop-blur-xl transition-all duration-300">
            <div className="mx-auto bg-[#B1C9EF]/40 text-[#395886] size-14 flex items-center justify-center rounded-2xl mb-4 border border-[#8AAEE0]/50 shadow-inner">
                <FileSpreadsheet className="size-7" />
            </div>
            <p className="font-extrabold text-[#395886] text-base">Belum Ada Data yang Cocok</p>
            <p className="text-[#395886]/70 text-xs mt-1 max-w-sm mx-auto font-medium leading-relaxed">
                Upload workbook XLSX untuk mengisi daftar ini atau sesuaikan kata kunci pencarian Anda.
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
    activeImportTasks,
    canRollbackLatestImport,
}: KompenResponHubPageProps): React.JSX.Element {
    const indexAction = isAdmin ? adminIndex : studentIndex;
    const tabs = isAdmin ? adminTabs : studentTabs;
    const [isUploadRequestActive, setIsUploadRequestActive] = useState(false);

    return (
        <>
            <Head title="Kompen Respon Hub" />

            {/* Main Wrapper Full Width (Tanpa batas hitam di tepi kiri/kanan) */}
            <main className="relative min-h-screen w-full bg-[#F0F3FA] text-[#395886] p-4 md:p-8 selection:bg-[#B1C9EF] selection:text-[#395886] overflow-x-hidden">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -left-40 size-[36rem] rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute top-1/3 -right-40 size-[36rem] rounded-full bg-[#B1C9EF]/40 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-20 left-1/4 size-[32rem] rounded-full bg-[#628ECB]/20 blur-3xl" />

                {/* Inner Content Container */}
                <div className="relative z-10 mx-auto w-full max-w-[1600px] flex flex-col gap-6">
                    {/* Page Header */}
                    <header className="flex flex-col justify-between gap-4 border-b border-[#D5DEEF] pb-6 md:flex-row md:items-end">
                        <div className="flex items-start gap-4">
                            <div className="bg-gradient-to-br from-[#395886] to-[#628ECB] text-white flex size-14 shrink-0 items-center justify-center rounded-2xl shadow-md">
                                <FileSpreadsheet className="size-7" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#628ECB]">
                                    {isAdmin
                                        ? 'System Administration Portal'
                                        : 'Akses Mahasiswa • Read Only'}
                                </p>
                                <h1 className="mt-1 text-3xl md:text-4xl font-black tracking-tight text-[#395886]">
                                    Kompen Respon Hub
                                </h1>
                                <p className="text-[#395886]/70 mt-1 max-w-2xl text-xs md:text-sm font-medium leading-relaxed">
                                    {isAdmin
                                        ? 'Impor workbook Sikompen dan kelola ringkasan Kompen/Respon maupun detail kehadiran.'
                                        : 'Lihat data Kompen/Respon dan Detail Kompen, lalu unduh hasil sesuai periode yang dipilih.'}
                                </p>
                            </div>
                        </div>
                        {isAdmin ? (
                            <div className="flex flex-wrap gap-2">
                                <Button asChild variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-all duration-300 rounded-2xl font-bold text-xs px-4 py-2.5 shadow-2xs active:scale-95">
                                    <Link href={adminSettings.url()} className="flex items-center gap-1.5">
                                        <Settings className="size-4" />
                                        Pengaturan
                                    </Link>
                                </Button>
                                <a
                                    className="bg-white/80 hover:bg-[#395886] hover:text-white border border-[#8AAEE0] text-[#395886] inline-flex h-9 items-center justify-center gap-1.5 rounded-2xl px-4 text-xs font-bold transition-all duration-300 shadow-2xs active:scale-95"
                                    href={downloadTemplate.url()}
                                >
                                    <Download className="size-4" />
                                    Download Template
                                </a>
                                <Button asChild variant="outline" className="border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition-all duration-300 rounded-2xl font-bold text-xs px-4 py-2.5 shadow-2xs active:scale-95">
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
                            </div>
                        ) : null}
                    </header>

                    {/* Alerts */}
                    {flash.success && (
                        <Alert className="bg-emerald-50/90 border border-emerald-200 text-emerald-900 rounded-2xl backdrop-blur-md shadow-sm animate-in fade-in slide-in-from-top-2 duration-300">
                            <CheckCircle2 className="size-5 text-emerald-600 animate-bounce" />
                            <AlertTitle className="font-bold text-emerald-950 text-base">Impor Workbook Berhasil</AlertTitle>
                            <AlertDescription className="text-emerald-700 text-xs mt-0.5">{flash.success}</AlertDescription>
                        </Alert>
                    )}

                    {flash.error && (
                        <Alert variant="destructive" className="bg-rose-50/90 border border-rose-200 text-rose-900 rounded-2xl backdrop-blur-md shadow-sm animate-in fade-in slide-in-from-top-2 duration-300">
                            <AlertCircle className="size-5 text-rose-600 animate-pulse" />
                            <AlertTitle className="font-bold text-rose-950 text-base">Tindakan Tidak Dapat Dijalankan</AlertTitle>
                            <AlertDescription className="text-rose-700 text-xs mt-0.5">{flash.error}</AlertDescription>
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
                                        tab === 'upload' || tab === 'imports'
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
                                    'flex items-center gap-2 border-b-2 px-4 py-2.5 text-xs font-bold transition-all duration-300 rounded-t-xl',
                                    activeTab === tab
                                        ? 'border-[#395886] text-[#395886] bg-white/70 shadow-2xs'
                                        : 'text-[#395886]/60 hover:text-[#395886] border-transparent hover:bg-white/40',
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
                        <p className="text-[#395886]/70 -mt-3 text-xs font-medium">
                            Tunggu sampai file selesai dikirim sebelum berpindah menu. Setelah itu impor akan berjalan di latar belakang.
                        </p>
                    ) : null}

                    {/* Main Views */}
                    {activeTab === 'upload' ? (
                        <UploadPanel
                            onUploadRequestActivityChange={setIsUploadRequestActive}
                        />
                    ) : null}

                    {activeTab === 'imports' ? (
                        <section className="grid gap-4">
                            <div className="bg-white/80 border border-white/80 flex flex-col justify-between gap-3 rounded-3xl p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl sm:flex-row sm:items-center">
                                <div>
                                    <h2 className="font-extrabold text-[#395886] text-lg">Audit Impor</h2>
                                    <p className="text-[#395886]/70 text-xs mt-0.5 font-medium">
                                        Riwayat upload dan penghapusan unggahan terakhir.
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
                                <div className="bg-white/80 border-2 border-dashed border-[#8AAEE0] rounded-3xl p-10 text-center text-xs text-[#395886]/70 font-bold backdrop-blur-xl">
                                    Belum ada riwayat upload atau rollback.
                                </div>
                            )}
                        </section>
                    ) : null}

                    {activeTab === 'students' || activeTab === 'details' ? (
                        <section className="flex flex-col gap-4">
                            <FilterPanel
                                key={activeTab}
                                activeTab={activeTab}
                                isAdmin={isAdmin}
                                filters={filters}
                                filterOptions={filterOptions}
                            />
                            {(activeTab === 'students' ? students : details)?.data
                                .length ? (
                                activeTab === 'students' && students ? (
                                    <StudentTable data={students} />
                                ) : details ? (
                                    <DetailTable data={details} />
                                ) : null
                            ) : (
                                <EmptyTableState />
                            )}
                        </section>
                    ) : null}
                </div>
            </main>
        </>
    );
}