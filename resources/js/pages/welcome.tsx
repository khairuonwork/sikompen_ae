import { Head, Link } from '@inertiajs/react';
import { GraduationCap, ShieldCheck, UserPlus, ArrowRight } from 'lucide-react';
import { create as adminLogin } from '@/actions/App/Http/Controllers/AdminAuthenticationController';
import { create as adminSetup } from '@/actions/App/Http/Controllers/KompenResponHubAdminSetupController';
import { Button } from '@/components/ui/button';
import { sikompenUrl } from '@/lib/sikompen-url';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as adminIndex } from '@/routes/admin/kompen-respon';
import { index as studentIndex } from '@/routes/student/kompen-respon';

type WelcomeProps = {
    isAdminAuthenticated: boolean;
    isInitialAdminSetupAvailable: boolean;
    isAdminSetupOpen: boolean;
};

export default function Welcome({
    isAdminAuthenticated,
    isInitialAdminSetupAvailable,
    isAdminSetupOpen,
}: WelcomeProps): React.JSX.Element {
    return (
        <>
            <Head title="Pilih Akses" />

            <main className="relative min-h-screen bg-[#F0F3FA] text-[#395886] flex items-center justify-center p-4 md:p-8 selection:bg-[#B1C9EF] selection:text-[#395886] overflow-hidden">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -left-40 size-[30rem] rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute top-1/2 -right-40 size-[30rem] rounded-full bg-[#B1C9EF]/40 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-20 left-1/4 size-96 rounded-full bg-[#628ECB]/20 blur-3xl" />

                <section className="relative z-10 w-full max-w-3xl space-y-10 animate-in fade-in zoom-in-95 duration-500">
                    {/* Header Landing */}
                    <div className="text-center space-y-3">
                        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/80 border border-[#8AAEE0]/50 text-[#395886] text-[10px] font-black uppercase tracking-[0.2em] shadow-2xs backdrop-blur-md hover:scale-105 transition-transform duration-300">
                            <span className="size-2 rounded-full bg-[#628ECB] animate-pulse" />
                            Kompen Respon Hub Platform
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black tracking-tight text-[#395886]">
                            Pilih Jenis Akses
                        </h1>
                        <p className="text-[#395886]/70 text-sm md:text-base max-w-xl mx-auto leading-relaxed font-medium">
                            Admin mengelola impor dokumen. Mahasiswa dapat melihat dan mengunduh data berdasarkan filter.
                        </p>
                    </div>

                    {/* Cards Grid */}
                    <div className="grid gap-6 md:grid-cols-2">
                        {/* Card Admin */}
                        <Card className="group bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_20px_45px_rgba(57,88,134,0.18)] transition-all duration-500 overflow-hidden backdrop-blur-xl flex flex-col justify-between hover:-translate-y-1.5">
                            <CardHeader className="space-y-4 pb-6 border-b border-[#F0F3FA]">
                                <div className="bg-gradient-to-br from-[#395886] to-[#628ECB] text-white flex size-14 items-center justify-center rounded-2xl shadow-md group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                                    <ShieldCheck className="size-7" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#628ECB]">
                                        Management Portal
                                    </p>
                                    <CardTitle className="text-2xl font-black text-[#395886]">
                                        Admin
                                    </CardTitle>
                                    <CardDescription className="text-[#395886]/70 text-xs md:text-sm mt-1 leading-relaxed font-normal">
                                        Login diperlukan untuk mengunggah workbook dan mengelola data sumber.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-6 space-y-3">
                                <Button
                                    asChild
                                    className="w-full bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl shadow-md hover:shadow-xl active:scale-95 transition-all duration-300 font-bold text-xs py-3"
                                >
                                    <Link
                                        href={
                                            isAdminAuthenticated
                                                ? sikompenUrl(adminIndex.url())
                                                : sikompenUrl(adminLogin.url())
                                        }
                                        className="flex items-center justify-center gap-2"
                                    >
                                        {isAdminAuthenticated
                                            ? 'Buka panel admin'
                                            : 'Login sebagai admin'}
                                        <ArrowRight className="size-4 group-hover:translate-x-1 transition-transform" />
                                    </Link>
                                </Button>

                                {(isInitialAdminSetupAvailable || isAdminSetupOpen) && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-all duration-300 rounded-2xl font-bold active:scale-95 text-xs py-2.5 shadow-2xs"
                                    >
                                        <Link href={sikompenUrl(adminSetup.url())} className="flex items-center justify-center gap-2">
                                            <UserPlus className="size-4" />
                                            {isInitialAdminSetupAvailable
                                                ? 'Buat akun admin pertama'
                                                : 'Daftar sebagai admin'}
                                        </Link>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>

                        {/* Card Mahasiswa */}
                        <Card className="group bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_20px_45px_rgba(57,88,134,0.18)] transition-all duration-500 overflow-hidden backdrop-blur-xl flex flex-col justify-between hover:-translate-y-1.5">
                            <CardHeader className="space-y-4 pb-6 border-b border-[#F0F3FA]">
                                <div className="bg-gradient-to-br from-[#395886] to-[#628ECB] text-white flex size-14 items-center justify-center rounded-2xl shadow-md group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                                    <GraduationCap className="size-7" />
                                </div>
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#628ECB]">
                                        Public View Access
                                    </p>
                                    <CardTitle className="text-2xl font-black text-[#395886]">
                                        Mahasiswa
                                    </CardTitle>
                                    <CardDescription className="text-[#395886]/70 text-xs md:text-sm mt-1 leading-relaxed font-normal">
                                        Akses baca saja untuk melihat Kompen/Respon, Detail Kompen, dan unduhan XLSX sesuai periode.
                                    </CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="pt-6">
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-all duration-300 rounded-2xl font-bold active:scale-95 text-xs py-3 shadow-2xs"
                                >
                                    <Link href={sikompenUrl(studentIndex.url())} className="flex items-center justify-center gap-2">
                                        Lihat data mahasiswa
                                        <ArrowRight className="size-4 group-hover:translate-x-1 transition-transform" />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </main>
        </>
    );
}
