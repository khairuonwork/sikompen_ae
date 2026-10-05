export type Student = {
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
    progress_status: "none" | "completed" | "warning_active";
    last_worked_at: string | null;
    has_summary_override: boolean;
    has_active_warning: boolean;
    warning: Pick<Warning, "id" | "letter_status" | "resolution"> | null;
};

export type Detail = {
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

export type Warning = {
    id: number;
    student_id: number | null;
    nim: string;
    nama_mahasiswa: string;
    kelas: string;
    periode_semester: string;
    letter_status: "not_created" | "draft" | "issued" | "cancelled";
    resolution: "outstanding" | "completed" | "needs_review";
    snapshot: { sisa_hutang_jam: number };
    reason: string | null;
    issued_at: string | null;
    cancelled_at: string | null;
};

export type ActivityLog = {
    id: number;
    event_type: string;
    subject_type: string;
    nim: string | null;
    subject_name: string | null;
    periode_semester: string | null;
    kelas: string | null;
    actor_type: string;
    actor_name: string | null;
    actor_email?: string | null;
    reason: string | null;
    before_state: ActivityState;
    after_state: ActivityState;
    metadata: Record<string, unknown> | null;
    occurred_at: string;
};

export type ActivityState = Record<string, unknown> | null;

export type StudentSearchResult = Pick<
    Student,
    "id" | "nim" | "nama_mahasiswa" | "kelas" | "tingkat" | "periode_semester"
>;

export type Cutoff = {
    id: number;
    periode_semester: string;
    deadline_at: string;
    timezone: string;
};

export type ImportAuditLog = {
    id: number;
    event_type: "upload" | "rollback" | "restore" | "activate";
    source_import_id: number | null;
    can_download_file: boolean;
    version_status: "active" | "stored" | "unavailable";
    version_imported_at: string | null;
    version_activated_at: string | null;
    actor_name: string | null;
    actor_email: string | null;
    periode_semester: string;
    original_filename: string;
    class_count: number;
    student_count: number;
    detail_count: number;
    quality_report?: {
        status: "passed";
        checks: { label: string; status: "passed"; detail: string }[];
    } | null;
    metadata: {
        restored?: boolean;
        restored_imports?: { id: number; original_filename: string }[];
        displaced_imports?: { id: number; original_filename: string }[];
        restored_classes?: string[];
    } | null;
    occurred_at: string;
};

export type ImportVersion = {
    id: number;
    periode_semester: string;
    original_filename: string;
    class_count: number;
    student_count: number;
    detail_count: number;
    uploaded_by_name: string | null;
    uploaded_by_email: string | null;
    imported_at: string | null;
    is_active: boolean;
    activated_at: string | null;
    activated_by_email: string | null;
    quality_report: {
        status: "passed";
        checks: { label: string; status: "passed"; detail: string }[];
    } | null;
};

export type ImportTask = {
    id: number;
    original_filename: string;
    status: "queued" | "processing" | "completed" | "failed";
    progress: number;
    progress_message: string;
    error_message: string | null;
    queued_at: string | null;
    completed_at: string | null;
};

export type ExportTask = {
    id: number;
    access_token: string;
    resource: "students" | "details" | "warnings";
    format: "xlsx" | "pdf";
    status: "queued" | "processing" | "completed" | "failed";
    progress: number;
    progress_message: string;
    download_filename: string | null;
    error_message: string | null;
    queued_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
};

export type Pagination<T> = {
    data: T[];
    links: { prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; total: number };
};

export type Filters = {
    nim?: string;
    nama?: string;
    search?: string;
    tingkat?: number;
    kelas?: string;
    periode_semester?: string;
    activity_event?: string;
    activity_actor?: string;
    per_page?: number;
};

export type FilterOptions = {
    tingkat: number[];
    kelas: string[];
    periode_semester: string[];
};

export type Dashboard = {
    summary: {
        total_students: number;
        outstanding_students: number;
        completed_students: number;
        outstanding_hours: number;
        warning_count: number;
        issued_warning_count: number;
        nearest_cutoff: {
            periode_semester: string;
            deadline_at: string;
            days_remaining: number;
        } | null;
        periods: {
            periode_semester: string;
            deadline_at: string | null;
            closed_at: string | null;
            status: "open" | "cutoff_passed" | "locked";
        }[];
    };
    worklist: {
        fixed_candidates: Student[];
        warnings_to_follow_up: Warning[];
    };
};

export type StudentOverview = {
    summary: Student;
    source: {
        import_filename: string | null;
        imported_at: string | null;
        total_kompensasi_jam: string;
        total_responsi_jam: string;
        sisa_hutang_jam: string;
    };
    details: Detail[];
    warnings: Warning[];
    activities: ActivityLog[];
};

export type KompenResponHubPageProps = {
    activeTab:
        | "upload"
        | "students"
        | "details"
        | "files"
        | "imports"
        | "warnings"
        | "activity";
    isAdmin: boolean;
    filters: Filters;
    filterOptions: FilterOptions;
    activityFilterOptions: { event_types: string[]; actor_emails: string[] };
    flash: { success: string | null; error: string | null };
    students: Pagination<Student> | null;
    details: Pagination<Detail> | null;
    imports: Pagination<ImportAuditLog> | null;
    importVersions: Pagination<ImportVersion> | null;
    warnings: Pagination<Warning> | null;
    warningCandidates: Pagination<Student> | null;
    rolledBackWarnings: Pagination<Warning> | null;
    activityLogs: Pagination<ActivityLog> | null;
    cutoffs: Cutoff[];
    activeImportTasks: ImportTask[];
    exportTasks: ExportTask[];
};
