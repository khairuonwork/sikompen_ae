import { Head, Link } from "@inertiajs/react";
import { AlertCircle, KeyRound, LogOut, UserRound } from "lucide-react";
import { destroy as logout } from "@/actions/App/Http/Controllers/StudentAuthenticationController";
import { edit as editPassword } from "@/actions/App/Http/Controllers/StudentPasswordController";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import type { Student } from "@/features/kompen-respon-hub/shared/types";
import { sikompenUrl } from "@/lib/sikompen-url";

type StudentProfileProps = {
    profile: {
        nim: string;
        nama_mahasiswa: string | null;
        warning_period: string | null;
        records: Student[];
        missing_data: string[];
    };
};

function formatHours(value: string): string {
    return `${new Intl.NumberFormat("id-ID", {
        maximumFractionDigits: 2,
        minimumFractionDigits: 0,
    }).format(Number(value))} jam`;
}

export default function StudentProfile({ profile }: StudentProfileProps): React.JSX.Element {
    return (
        <>
            <Head title="Profil Mahasiswa" />

            <main className="relative min-h-screen overflow-x-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886] md:p-8">
                <div className="pointer-events-none absolute -top-40 -left-40 size-[36rem] rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -right-40 -bottom-20 size-[32rem] rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <div className="relative z-10 mx-auto grid w-full max-w-5xl gap-5">
                    <header className="flex flex-col justify-between gap-4 border-b border-[#D5DEEF] pb-5 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                                <UserRound className="size-6" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">Akun Mahasiswa</p>
                                <h1 className="text-2xl font-black tracking-tight text-[#395886]">Profil Saya</h1>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild className="rounded-2xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                <Link href={sikompenUrl(editPassword.url())}>
                                    <KeyRound className="size-4" /> Ganti password
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="rounded-2xl border-rose-300 bg-white/80 text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white">
                                <Link href={sikompenUrl(logout.url())} method="post" as="button">
                                    <LogOut className="size-4" /> Keluar
                                </Link>
                            </Button>
                        </div>
                    </header>

                    {profile.missing_data.length > 0 ? (
                        <Alert className="rounded-2xl border-amber-200 bg-amber-50 text-amber-950 shadow-sm">
                            <AlertCircle className="size-5 text-amber-700" />
                            <AlertTitle className="font-bold">Data Anda belum lengkap</AlertTitle>
                            <AlertDescription className="text-amber-900/80">
                                <p>Silakan hubungi admin jurusan untuk memperbarui data berikut.</p>
                                <ul className="mt-2 list-disc space-y-1 pl-4">
                                    {profile.missing_data.map((item) => <li key={item}>{item}</li>)}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <section className="grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
                        <Card className="rounded-3xl border-white/80 bg-white/85 py-0 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                            <CardHeader className="border-b border-[#F0F3FA] px-5 py-5">
                                <CardTitle className="text-lg font-extrabold text-[#395886]">Identitas mahasiswa</CardTitle>
                                <CardDescription className="text-xs text-[#395886]/65">Data identitas yang tersedia di sistem.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 px-5 py-5">
                                <div>
                                    <p className="text-[10px] font-black tracking-[0.15em] text-[#628ECB] uppercase">Nama</p>
                                    <p className="mt-1 text-base font-extrabold text-[#395886]">{profile.nama_mahasiswa ?? "Belum tersedia"}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-black tracking-[0.15em] text-[#628ECB] uppercase">NIM</p>
                                    <p className="mt-1 font-mono text-sm font-bold text-[#395886]">{profile.nim}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="rounded-3xl border-white/80 bg-white/85 py-0 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                            <CardHeader className="border-b border-[#F0F3FA] px-5 py-5">
                                <CardTitle className="text-lg font-extrabold text-[#395886]">Ringkasan jam Kompen dan Respon</CardTitle>
                                <CardDescription className="text-xs text-[#395886]/65">
                                    {profile.warning_period === null
                                        ? "Menunggu periode Surat Peringatan aktif ditetapkan."
                                        : `Ditampilkan untuk periode Surat Peringatan ${profile.warning_period}.`}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 px-5 py-5">
                                {profile.records.length > 0 ? profile.records.map((record) => (
                                    <article key={record.id} className="rounded-2xl border border-[#D5DEEF] bg-white p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p className="font-extrabold text-[#395886]">{record.periode_semester}</p>
                                                <p className="mt-0.5 text-xs font-medium text-[#395886]/65">Kelas {record.kelas}</p>
                                            </div>
                                            <span className="rounded-full bg-[#D5DEEF] px-2.5 py-1 text-[10px] font-black text-[#395886]">Sisa {formatHours(record.effective_sisa_hutang_jam)}</span>
                                        </div>
                                        <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs sm:grid-cols-4">
                                            <div><dt className="text-[#395886]/60">Kompen</dt><dd className="mt-0.5 font-bold text-[#395886]">{formatHours(record.effective_total_kompensasi_jam)}</dd></div>
                                            <div><dt className="text-[#395886]/60">Respon</dt><dd className="mt-0.5 font-bold text-[#395886]">{formatHours(record.effective_total_responsi_jam)}</dd></div>
                                            <div><dt className="text-[#395886]/60">Dikerjakan</dt><dd className="mt-0.5 font-bold text-[#395886]">{formatHours(record.effective_kompensasi_dikerjakan_jam)}</dd></div>
                                            <div><dt className="text-[#395886]/60">Sisa</dt><dd className="mt-0.5 font-bold text-[#395886]">{formatHours(record.effective_sisa_hutang_jam)}</dd></div>
                                        </dl>
                                    </article>
                                )) : <p className="rounded-2xl border border-dashed border-[#8AAEE0] bg-[#F0F3FA]/60 px-4 py-6 text-center text-xs font-semibold text-[#395886]/65">Data Kompen dan Respon belum tersedia.</p>}
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </main>
        </>
    );
}
