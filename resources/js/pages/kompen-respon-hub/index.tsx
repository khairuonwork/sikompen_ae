import { Form, Head, Link } from '@inertiajs/react';
import {
    Download,
    FileText,
    FileSpreadsheet,
    ListFilter,
    Search,
    Settings,
    UploadCloud,
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
        <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm">
            <span>
                Halaman {data.meta.current_page} dari {data.meta.last_page} ·{' '}
                {data.meta.total} data
            </span>
            <div className="flex gap-2">
                {data.links.prev ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={data.links.prev}>Sebelumnya</Link>
                    </Button>
                ) : null}
                {data.links.next ? (
                    <Button asChild size="sm" variant="outline">
                        <Link href={data.links.next}>Berikutnya</Link>
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
        <section className="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1150px] text-sm">
                    <thead className="bg-muted/60 text-muted-foreground text-left text-xs tracking-wide uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th
                                    key={heading}
                                    className="px-3 py-3 font-medium"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {data.data.map((student) => (
                            <tr key={student.id} className="hover:bg-muted/40">
                                <td className="px-3 py-3">{student.tingkat}</td>
                                <td className="px-3 py-3 font-mono">
                                    {student.nim}
                                </td>
                                <td className="px-3 py-3 font-medium">
                                    {student.nama_mahasiswa}
                                </td>
                                <td className="px-3 py-3">{student.kelas}</td>
                                <td className="px-3 py-3">
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
                                        className="px-3 py-3 text-right tabular-nums"
                                    >
                                        {number(value)}
                                    </td>
                                ))}
                                <td className="px-3 py-3">
                                    <a
                                        className="text-primary hover:underline"
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
        <section className="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1100px] text-sm">
                    <thead className="bg-muted/60 text-muted-foreground text-left text-xs tracking-wide uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th
                                    key={heading}
                                    className="px-3 py-3 font-medium"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {data.data.map((detail) => (
                            <tr key={detail.id} className="hover:bg-muted/40">
                                <td className="px-3 py-3 whitespace-nowrap">
                                    {detail.tanggal}
                                </td>
                                <td className="px-3 py-3 font-mono">
                                    {detail.nim}
                                </td>
                                <td className="px-3 py-3 font-medium">
                                    {detail.nama_mahasiswa}
                                </td>
                                <td className="px-3 py-3">{detail.kelas}</td>
                                <td className="px-3 py-3">
                                    {detail.mata_kuliah}
                                </td>
                                <td className="px-3 py-3">
                                    {detail.nama_dosen}
                                </td>
                                <td className="px-3 py-3">
                                    {detail.jenis_pertemuan}
                                </td>
                                <td className="px-3 py-3">{detail.presensi}</td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {detail.menit_keterlambatan}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {number(detail.jam_kompensasi)}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {number(detail.jam_responsi)}
                                </td>
                                <td className="max-w-60 px-3 py-3">
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
        'Admin pelaksana',
        'Periode',
        'Nama file',
        'File',
        'Kelas',
        'Mahasiswa',
        'Detail',
    ];

    return (
        <section className="bg-card overflow-hidden rounded-xl border shadow-sm">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1130px] text-sm">
                    <thead className="bg-muted/60 text-muted-foreground text-left text-xs tracking-wide uppercase">
                        <tr>
                            {headings.map((heading) => (
                                <th
                                    key={heading}
                                    className="px-3 py-3 font-medium"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {data.data.map((auditLog) => (
                            <tr
                                key={auditLog.id}
                                className="hover:bg-muted/40"
                            >
                                <td className="px-3 py-3 whitespace-nowrap">
                                    {auditLog.event_type === 'upload'
                                        ? 'Upload'
                                        : 'Rollback · dihapus'}
                                </td>
                                <td className="px-3 py-3 whitespace-nowrap">
                                    {new Intl.DateTimeFormat('id-ID', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }).format(new Date(auditLog.occurred_at))}
                                </td>
                                <td className="px-3 py-3">
                                    <p className="font-medium">
                                        {auditLog.actor_name ?? '—'}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {auditLog.actor_email ?? '—'}
                                    </p>
                                </td>
                                <td className="px-3 py-3">
                                    {auditLog.periode_semester}
                                </td>
                                <td className="max-w-64 truncate px-3 py-3">
                                    {auditLog.original_filename}
                                </td>
                                <td className="px-3 py-3">
                                    {auditLog.can_download_file &&
                                    auditLog.source_import_id !== null ? (
                                        <Button asChild size="sm" variant="outline">
                                            <a
                                                href={downloadUploadedWorkbook.url(
                                                    auditLog.source_import_id,
                                                )}
                                            >
                                                <Download className="size-4" />
                                                Unduh
                                            </a>
                                        </Button>
                                    ) : (
                                        <span className="text-muted-foreground text-xs">
                                            Tidak tersedia
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {auditLog.class_count}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
                                    {auditLog.student_count}
                                </td>
                                <td className="px-3 py-3 text-right tabular-nums">
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
                >
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
            className="bg-muted h-2 overflow-hidden rounded-full"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={progress}
            aria-label={`Progres impor ${progress}%`}
        >
            <div
                className="bg-primary h-full rounded-full transition-[width] duration-300"
                style={{ width: `${progress}%` }}
            />
        </div>
    );
}

function UploadProgressPanel({ progress }: { progress: number }): React.JSX.Element {
    return (
        <div className="bg-muted/50 grid gap-2 rounded-lg border p-3">
            <div className="flex items-center justify-between gap-3 text-xs">
                <span className="font-medium">Mengirim workbook ke server</span>
                <span className="text-muted-foreground tabular-nums">
                    {progress}%
                </span>
            </div>
            <ProgressBar value={progress} />
            <p className="text-muted-foreground text-xs">
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
                    className="bg-card grid gap-2 rounded-xl border p-4 shadow-sm"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-medium">
                                Memproses {importTask.original_filename}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {importTask.progress_message}
                            </p>
                        </div>
                        <span className="text-muted-foreground text-sm font-medium tabular-nums">
                            {importTask.progress}%
                        </span>
                    </div>
                    <ProgressBar value={importTask.progress} />
                    {importTask.error_message ? (
                        <p className="text-destructive text-sm">
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
        <Card className="mx-auto w-full max-w-3xl">
            <CardHeader>
                <CardTitle>Upload workbook</CardTitle>
                <CardDescription>
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
                        <CardContent className="grid gap-2">
                            <Label htmlFor="uploader-name">
                                Nama admin yang mengunggah
                            </Label>
                            <Input
                                id="uploader-name"
                                name="uploader_name"
                                autoComplete="name"
                                maxLength={100}
                                onDragOver={preventDropIntoUploaderName}
                                onDrop={preventDropIntoUploaderName}
                                required
                            />
                            {errors.uploader_name ? (
                                <p className="text-destructive text-sm">
                                    {errors.uploader_name}
                                </p>
                            ) : null}
                            <Label htmlFor="kompen-respon-workbook">
                                Workbook XLSX
                            </Label>
                            <Input
                                id="kompen-respon-workbook"
                                name="file"
                                type="file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                required
                            />
                            <p className="text-muted-foreground text-xs">
                                Maksimum 20 MB. Periode pada filter akan muncul
                                otomatis setelah data berhasil diimpor.
                            </p>
                            {errors.file ? (
                                <p className="text-destructive text-sm">
                                    {errors.file}
                                </p>
                            ) : null}
                            {processing ? (
                                <UploadProgressPanel
                                    progress={progress?.percentage ?? 0}
                                />
                            ) : null}
                        </CardContent>
                        <CardFooter className="justify-end border-t pt-6">
                            <Button type="submit" disabled={processing}>
                                <UploadCloud className="size-4" />
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
            className="bg-card grid gap-3 rounded-xl border p-4 lg:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(140px,auto))]"
        >
            <input name="tab" type="hidden" value={activeTab} />
            <input name="tingkat" type="hidden" value={tingkat} />
            <input name="kelas" type="hidden" value={kelas} />
            <input name="periode_semester" type="hidden" value={periode} />
            <input name="per_page" type="hidden" value={perPage} />
            <Input
                name="search"
                placeholder="Cari nama atau NIM"
                defaultValue={filters.search}
            />
            <Select
                value={tingkat || undefined}
                onValueChange={(value) =>
                    setTingkat(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Semua tingkat" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua tingkat</SelectItem>
                    {filterOptions.tingkat.map((option) => (
                        <SelectItem key={option} value={option.toString()}>
                            Tingkat {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={kelas || undefined}
                onValueChange={(value) =>
                    setKelas(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Semua kelas" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua kelas</SelectItem>
                    {filterOptions.kelas.map((option) => (
                        <SelectItem key={option} value={option}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <Select
                value={periode || undefined}
                onValueChange={(value) =>
                    setPeriode(value === 'all' ? '' : value)
                }
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Semua periode upload" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua periode upload</SelectItem>
                    {filterOptions.periode_semester.map((option) => (
                        <SelectItem key={option} value={option}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <div className="flex gap-2">
                <Select value={perPage} onValueChange={setPerPage}>
                    <SelectTrigger className="w-22">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {[15, 30, 50, 100].map((option) => (
                            <SelectItem key={option} value={option.toString()}>
                                {option} data
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button type="submit" className="grow">
                    <Search className="size-4" />
                    Terapkan
                </Button>
            </div>
            <div className="col-span-full flex flex-wrap items-center justify-between gap-3">
                <Link
                    href={indexAction.url({ query: { tab: activeTab } })}
                    className="text-muted-foreground hover:text-foreground text-sm"
                >
                    Reset filter
                </Link>
                {spreadsheetDownloadUrl && pdfDownloadUrl ? (
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="outline">
                            <a href={spreadsheetDownloadUrl}>
                                <FileSpreadsheet className="size-4" />
                                Download XLSX
                            </a>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <a href={pdfDownloadUrl}>
                                <FileText className="size-4" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                ) : (
                    <span className="text-muted-foreground text-xs">
                        Pilih periode untuk mengunduh data.
                    </span>
                )}
            </div>
        </Form>
    );
}

function EmptyTableState(): React.JSX.Element {
    return (
        <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
            <FileSpreadsheet className="mx-auto mb-3 size-6" />
            Belum ada data yang cocok. Upload workbook XLSX untuk mengisi daftar
            ini.
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

            <main className="bg-background mx-auto flex min-h-screen w-full max-w-[1600px] flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-col justify-between gap-4 border-b pb-6 md:flex-row md:items-end">
                    <div className="flex gap-3">
                        <div className="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg">
                            <FileSpreadsheet className="size-5" />
                        </div>
                        <div>
                            <p className="text-primary text-sm font-medium">
                                {isAdmin
                                    ? 'Panel admin'
                                    : 'Akses mahasiswa · baca saja'}
                            </p>
                            <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                                Kompen Respon Hub
                            </h1>
                            <p className="text-muted-foreground mt-2 max-w-2xl text-sm">
                                {isAdmin
                                    ? 'Impor workbook Sikompen dan kelola ringkasan Kompen/Respon maupun detail kehadiran.'
                                    : 'Lihat data Kompen/Respon dan Detail Kompen, lalu unduh hasil sesuai periode yang dipilih.'}
                            </p>
                        </div>
                    </div>
                    {isAdmin ? (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={adminSettings.url()}>
                                    <Settings className="size-4" />
                                    Pengaturan
                                </Link>
                            </Button>
                            <a
                                className="bg-background hover:bg-accent inline-flex h-9 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium shadow-xs"
                                href={downloadTemplate.url()}
                            >
                                <Download className="size-4" />
                                Download template
                            </a>
                            <Button asChild variant="outline">
                                <Link
                                    href={logout.url()}
                                    method="post"
                                    as="button"
                                >
                                    Keluar
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </header>

                {flash.success ? (
                    <Alert>
                        <UploadCloud />
                        <AlertTitle>Impor workbook</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                ) : null}

                {flash.error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Tindakan tidak dapat dijalankan</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                ) : null}

                {isAdmin ? (
                    <ImportProgressPanel
                        initialImportTasks={activeImportTasks}
                    />
                ) : null}

                <nav
                    className="flex flex-wrap gap-2 border-b"
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
                                'flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium',
                                activeTab === tab
                                    ? 'border-primary text-primary'
                                    : 'text-muted-foreground hover:text-foreground border-transparent',
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
                    <p className="text-muted-foreground -mt-3 text-xs">
                        Tunggu sampai file selesai dikirim sebelum berpindah
                        menu. Setelah itu impor akan berjalan di latar belakang.
                    </p>
                ) : null}

                {activeTab === 'upload' ? (
                    <UploadPanel
                        onUploadRequestActivityChange={setIsUploadRequestActive}
                    />
                ) : null}

                {activeTab === 'imports' ? (
                    <section className="grid gap-4">
                        <div className="flex flex-col justify-between gap-3 rounded-xl border p-4 sm:flex-row sm:items-center">
                            <div>
                                <h2 className="font-semibold">Audit impor</h2>
                                <p className="text-muted-foreground text-sm">
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
                            <div className="text-muted-foreground rounded-xl border border-dashed p-10 text-center text-sm">
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
            </main>
        </>
    );
}
