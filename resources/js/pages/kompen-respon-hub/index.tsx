import { Head, Link, router } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    FileSpreadsheet,
    ListFilter,
    LogOut,
    Download,
    Settings,
} from "lucide-react";
import { useState } from "react";
import { adminStudentOverview } from "@/actions/App/Http/Controllers/KompenResponHubController";
import { downloadTemplate } from "@/actions/App/Http/Controllers/KompenResponHubImportController";
import { destroy as logout } from "@/actions/App/Http/Controllers/AdminAuthenticationController";
import { settings as adminSettings } from "@/actions/App/Http/Controllers/KompenResponHubAdminSetupController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import { index as studentIndex } from "@/routes/student/kompen-respon";
import { StudentOverviewPanel } from "@/features/kompen-respon-hub/dashboard/dashboard-panel";
import { GlobalStudentSearch } from "@/features/kompen-respon-hub/dashboard/global-student-search";
import { DetailCorrectionPanel } from "@/features/kompen-respon-hub/detail-kompen/detail-correction-panel";
import { DetailTable } from "@/features/kompen-respon-hub/detail-kompen/detail-table";
import { ExportProgressPanel } from "@/features/kompen-respon-hub/export/export-progress-panel";
import { EditModeControl } from "@/features/kompen-respon-hub/kompen-respon/edit-mode-control";
import {
    StudentCorrectionPanel,
} from "@/features/kompen-respon-hub/kompen-respon/student-correction-panel";
import { StudentTable } from "@/features/kompen-respon-hub/kompen-respon/student-table";
import { ImportAuditLogTable } from "@/features/kompen-respon-hub/log-upload/import-audit-log-table";
import { ImportVersionList } from "@/features/kompen-respon-hub/list-file/import-version-list";
import { ActivityFilterPanel, ActivityLogTable } from "@/features/kompen-respon-hub/riwayat-aktivitas/activity-panel";
import { EmptyTableState, TableGuide } from "@/features/kompen-respon-hub/shared/components/feedback";
import { FilterPanel } from "@/features/kompen-respon-hub/shared/components/data-filter-panel";
import { adminTabs, studentTabs } from "@/features/kompen-respon-hub/shared/constants";
import type {
    Detail,
    KompenResponHubPageProps,
    Student,
    StudentOverview,
    Warning,
} from "@/features/kompen-respon-hub/shared/types";
import { WarningPanel } from "@/features/kompen-respon-hub/surat-peringatan/warning-panel";
import { ImportProgressPanel } from "@/features/kompen-respon-hub/upload/import-progress-panel";
import { UploadPanel } from "@/features/kompen-respon-hub/upload/upload-panel";

export default function KompenResponHubIndex({
    activeTab,
    isAdmin,
    filters,
    filterOptions,
    activityFilterOptions,
    flash,
    students,
    details,
    imports,
    importVersions,
    activeImportVersions,
    warnings,
    warningCandidates,
    rolledBackWarnings,
    activityLogs,
    cutoffs,
    activeImportTasks,
    exportTasks,
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
    const [studentOverview, setStudentOverview] =
        useState<StudentOverview | null>(null);
    const [isStudentOverviewLoading, setIsStudentOverviewLoading] =
        useState(false);

    async function loadStudentOverview(studentId: number): Promise<void> {
        setIsStudentOverviewLoading(true);

        try {
            const response = await fetch(adminStudentOverview.url(studentId), {
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                throw new Error("Profil mahasiswa tidak dapat dimuat.");
            }

            const payload = (await response.json()) as {
                data: StudentOverview;
            };
            setStudentOverview(payload.data);
        } catch {
            window.alert(
                "Profil mahasiswa tidak dapat dimuat. Silakan coba lagi.",
            );
        } finally {
            setIsStudentOverviewLoading(false);
        }
    }

    function openStudentOverview(studentId: number): void {
        if (activeTab !== "students") {
            router.get(
                adminIndex.url({ query: { tab: "students" } }),
                {},
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        void loadStudentOverview(studentId);
                    },
                },
            );

            return;
        }

        void loadStudentOverview(studentId);
    }

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
                                        ? "System Administration Portal"
                                        : "Akses Mahasiswa • Read Only"}
                                </p>
                                <h1 className="mt-1 text-3xl font-black tracking-tight text-[#395886] md:text-4xl">
                                    Kompen Respon Hub
                                </h1>
                                <p className="mt-1 max-w-2xl text-xs leading-relaxed font-medium text-[#395886]/70 md:text-sm">
                                    {isAdmin
                                        ? "Impor workbook Sikompen dan kelola ringkasan Kompen/Respon maupun detail kehadiran."
                                        : "Lihat data Kompen/Respon dan Detail Kompen, lalu unduh hasil sesuai periode yang dipilih."}
                                </p>
                            </div>
                        </div>
                        <div className="flex w-full flex-wrap items-center gap-2 md:w-auto md:justify-end">
                            {isAdmin ? (
                                <GlobalStudentSearch
                                    isProfileLoading={isStudentOverviewLoading}
                                    onSelectStudent={openStudentOverview}
                                />
                            ) : null}
                            {isAdmin ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-2xl border-[#8AAEE0] bg-white/80 px-4 py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white active:scale-95"
                                >
                                    <Link
                                        href={adminIndex.url({
                                            query: { tab: "upload" },
                                        })}
                                        className="flex items-center gap-1.5"
                                    >
                                        <ArrowLeft className="size-4" />
                                        Kembali
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled
                                    className="rounded-2xl border-[#8AAEE0] bg-white/80 px-4 py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 disabled:opacity-100"
                                >
                                    <ArrowLeft className="size-4" />
                                    Kembali
                                </Button>
                            )}
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
                    <ExportProgressPanel initialExportTasks={exportTasks} />

                    {/* Navigation Tabs */}
                    <nav
                        className="flex flex-wrap gap-2 border-b border-[#D5DEEF] pb-1"
                        aria-label={isAdmin ? "Menu admin" : "Menu mahasiswa"}
                    >
                        {tabs.map(([tab, label]) => (
                            <Link
                                key={tab}
                                href={indexAction.url({
                                    query:
                                        tab === "upload" ||
                                        tab === "files" ||
                                        tab === "imports" ||
                                        tab === "warnings" ||
                                        tab === "activity"
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
                                    "flex items-center gap-2 rounded-t-xl border-b-2 px-4 py-2.5 text-xs font-bold transition-all duration-300",
                                    activeTab === tab
                                        ? "border-[#395886] bg-white/70 text-[#395886] shadow-2xs"
                                        : "border-transparent text-[#395886]/60 hover:bg-white/40 hover:text-[#395886]",
                                    isUploadRequestActive &&
                                        "pointer-events-none cursor-not-allowed opacity-50",
                                )}
                            >
                                {tab !== "upload" ? (
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
                    {activeTab === "upload" ? (
                        <UploadPanel
                            onUploadRequestActivityChange={
                                setIsUploadRequestActive
                            }
                        />
                    ) : null}

                    {activeTab === "imports" ? (
                        <section className="grid gap-4">
                            <div className="flex flex-col justify-between gap-3 rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl sm:flex-row sm:items-center">
                                <div>
                                    <h2 className="text-lg font-extrabold text-[#395886]">
                                        Log Upload
                                    </h2>
                                    <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                                        Riwayat permanen unggah dan pemilihan
                                        workbook. Gunakan List File untuk melihat
                                        sumber data yang sedang digunakan.
                                    </p>
                                </div>
                            </div>
                            <TableGuide
                                title="Panduan Log Upload"
                                items={[
                                    "Setiap baris mencatat satu tindakan: unggah, aktivasi, rollback, atau pemulihan data.",
                                    "Periode dan nama file menjelaskan workbook yang terdampak; nama file yang sama tetap dapat menjadi versi berbeda bila waktu unggahnya berbeda.",
                                    "Waktu tindakan menunjukkan kapan aktivitas dilakukan. Log ini bersifat audit dan tidak menentukan sumber data aktif.",
                                    "Untuk melihat workbook yang sedang dipakai pada setiap periode, buka List File.",
                                ]}
                            />
                            {imports?.data.length ? (
                                <ImportAuditLogTable data={imports} />
                            ) : (
                                <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-10 text-center text-xs font-bold text-[#395886]/70 backdrop-blur-xl">
                                    Belum ada riwayat upload.
                                </div>
                            )}
                        </section>
                    ) : null}

                    {activeTab === "files" && isAdmin ? (
                        <section className="grid gap-4">
                            <div className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                                <h2 className="text-lg font-extrabold text-[#395886]">
                                    List File
                                </h2>
                                <p className="mt-0.5 text-xs font-medium text-[#395886]/70">
                                    Tentukan satu workbook yang digunakan untuk setiap periode. Mengganti versi hanya mengganti data sumber periode tersebut; koreksi manual, progres, SP, dan riwayat tetap dipertahankan.
                                </p>
                            </div>
                            <TableGuide
                                title="Panduan List File"
                                items={[
                                    "Bagian Sumber data yang digunakan merangkum satu workbook aktif pada setiap periode.",
                                    "Setiap periode hanya dapat memiliki satu workbook aktif, sedangkan periode yang berbeda dapat memakai workbook aktif yang berbeda.",
                                    "Daftar semua versi menyimpan riwayat workbook yang siap dipilih. Klik Jadikan aktif untuk mengganti sumber data periode terkait.",
                                ]}
                            />
                            {importVersions?.data.length ? (
                                <ImportVersionList
                                    activeVersions={activeImportVersions}
                                    data={importVersions}
                                />
                            ) : (
                                <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-10 text-center text-xs font-bold text-[#395886]/70">
                                    Belum ada workbook yang siap dipilih. Upload dokumen terlebih dahulu.
                                </div>
                            )}
                        </section>
                    ) : null}

                    {activeTab === "warnings" && isAdmin ? (
                        <WarningPanel
                            cutoffs={cutoffs}
                            filterOptions={filterOptions}
                            filters={filters}
                            warnings={warnings}
                            warningCandidates={warningCandidates}
                            rolledBackWarnings={rolledBackWarnings}
                            selectedWarning={selectedWarning}
                            onSelectWarning={setSelectedWarning}
                            onCloseWarning={() => setSelectedWarning(null)}
                        />
                    ) : null}

                    {activeTab === "activity" && isAdmin ? (
                        <section className="grid gap-4">
                            <ActivityFilterPanel
                                filters={filters}
                                filterOptions={filterOptions}
                                activityFilterOptions={activityFilterOptions}
                            />
                            {activityLogs?.data.length ? (
                                <ActivityLogTable data={activityLogs} />
                            ) : (
                                <div className="rounded-3xl border-2 border-dashed border-[#8AAEE0] bg-white/80 p-10 text-center text-xs font-bold text-[#395886]/70">
                                    Belum ada aktivitas yang tercatat.
                                </div>
                            )}
                        </section>
                    ) : null}

                    {activeTab === "students" || activeTab === "details" ? (
                        <section className="flex flex-col gap-4">
                            {isAdmin && studentOverview ? (
                                <StudentOverviewPanel
                                    overview={studentOverview}
                                    onClose={() => setStudentOverview(null)}
                                />
                            ) : null}
                            {isAdmin ? (
                                <div className="flex justify-end">
                                    <EditModeControl
                                        enabled={isEditMode}
                                        onChange={(enabled) => {
                                            setIsEditMode(enabled);
                                            if (!enabled) {
                                                setSelectedStudent(null);
                                                setSelectedDetail(null);
                                            }
                                        }}
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
                            <TableGuide
                                title={
                                    activeTab === "students"
                                        ? "Panduan Kompen dan Respon"
                                        : "Panduan Detail Kompen"
                                }
                                items={
                                    activeTab === "students"
                                        ? [
                                              "T, S, I, dan B adalah jam Terlambat, Sakit, Izin, dan Bolos dari data sumber.",
                                              "Kompen dan Responsi adalah total kewajiban; nilai selesai dan sisa mengikuti koreksi admin bila ada.",
                                              "Status hanya tampil setelah cutoff: Selesai bila sisa jam nol, atau SP aktif bila surat masih berjalan.",
                                          ]
                                        : [
                                              "Setiap baris mewakili satu kejadian perkuliahan yang menghasilkan jam Kompen atau Responsi.",
                                              "Terlambat memakai satuan menit; Kompen dan Responsi memakai satuan jam.",
                                              "Keterangan menyimpan konteks kejadian dari workbook atau koreksi admin.",
                                          ]
                                }
                            />
                            {isAdmin &&
                            isEditMode &&
                            activeTab === "students" &&
                            selectedStudent ? (
                                <StudentCorrectionPanel
                                    student={selectedStudent}
                                    onClose={() => setSelectedStudent(null)}
                                />
                            ) : null}
                            {isAdmin &&
                            isEditMode &&
                            activeTab === "details" &&
                            selectedDetail ? (
                                <DetailCorrectionPanel
                                    detail={selectedDetail}
                                    onClose={() => setSelectedDetail(null)}
                                />
                            ) : null}
                            {(activeTab === "students" ? students : details)
                                ?.data.length ? (
                                activeTab === "students" && students ? (
                                    <StudentTable
                                        data={students}
                                        isEditMode={isAdmin && isEditMode}
                                        onSelect={setSelectedStudent}
                                        onOpenProfile={
                                            isAdmin
                                                ? (student) => openStudentOverview(student.id)
                                                : undefined
                                        }
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
                            {isStudentOverviewLoading ? (
                                <p className="text-center text-xs font-semibold text-[#395886]/70">
                                    Memuat profil mahasiswa…
                                </p>
                            ) : null}
                        </section>
                    ) : null}
                </div>
            </main>
        </>
    );
}
