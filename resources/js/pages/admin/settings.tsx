import React, { useState } from 'react';
import { Form, Head, Link } from '@inertiajs/react';
import {
    Clock3,
    Copy,
    Check,
    LockKeyhole,
    Settings,
    UserPlus,
    ArrowLeft,
    ShieldCheck,
    AlertCircle
} from 'lucide-react';
import {
    disable,
    enable,
} from '@/actions/App/Http/Controllers/KompenResponHubAdminSetupController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { sikompenForm, sikompenUrl } from '@/lib/sikompen-url';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as adminIndex } from '@/routes/admin/kompen-respon';

type AdminSettingsProps = {
    setupWindow: {
        isOpen: boolean;
        expiresAt: string | null;
    };
    flash: {
        adminSetupCode: string | null;
        success: string | null;
    };
};

function formatExpiry(expiresAt: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
    }).format(new Date(expiresAt));
}

export default function AdminSettings({
    setupWindow,
    flash,
}: AdminSettingsProps): React.JSX.Element {
    const [copied, setCopied] = useState(false);

    const handleCopy = (code: string) => {
        navigator.clipboard.writeText(code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <>
            <Head title="Pengaturan Admin" />

            {/* Background Container dengan Efek Ambient Mesh Blur */}
            <main className="relative min-h-screen bg-[#F0F3FA] text-[#395886] p-4 md:p-10 flex justify-center items-start selection:bg-[#B1C9EF] selection:text-[#395886] overflow-hidden">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -left-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute top-1/2 -right-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-20 left-1/3 size-80 rounded-full bg-[#628ECB]/20 blur-3xl" />

                <section className="relative z-10 w-full max-w-3xl space-y-6 animate-in fade-in zoom-in-95 duration-500">
                    {/* Header Section */}
                    <header className="group flex flex-wrap items-center justify-between gap-4 bg-white/70 backdrop-blur-xl p-6 rounded-3xl border border-white/80 shadow-[0_8px_30px_rgb(0,0,0,0.04)] transition-all duration-300 hover:shadow-[0_12px_40px_rgba(98,142,203,0.2)] hover:-translate-y-0.5">
                        <div className="flex items-center gap-4">
                            <div className="bg-gradient-to-br from-[#395886] to-[#628ECB] text-white flex size-14 items-center justify-center rounded-2xl shadow-md group-hover:scale-105 group-hover:rotate-6 transition-all duration-300">
                                <Settings className="size-7 group-hover:rotate-180 transition-transform duration-700 ease-in-out" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#628ECB]">
                                    System Control • Administration
                                </p>
                                <h1 className="text-2xl md:text-3xl font-black tracking-tight text-[#395886]">
                                    Pengaturan Admin
                                </h1>
                            </div>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-all duration-300 rounded-2xl font-bold text-xs px-4 py-2.5 active:scale-95 shadow-2xs"
                        >
                            <Link href={sikompenUrl(adminIndex.url())} className="flex items-center gap-2">
                                <ArrowLeft className="size-4 transition-transform group-hover:-translate-x-1" />
                                Kembali ke panel
                            </Link>
                        </Button>
                    </header>

                    {/* Alert Flash Success */}
                    {flash.success && (
                        <Alert className="bg-emerald-50/90 border border-emerald-200 text-emerald-900 rounded-2xl backdrop-blur-md shadow-sm animate-in fade-in slide-in-from-top-3 duration-300">
                            <ShieldCheck className="size-5 text-emerald-600 animate-bounce" />
                            <AlertTitle className="font-bold text-emerald-950 text-base">Pengaturan Diperbarui</AlertTitle>
                            <AlertDescription className="text-emerald-700 text-xs mt-0.5">{flash.success}</AlertDescription>
                        </Alert>
                    )}

                    {/* Alert Setup Code */}
                    {flash.adminSetupCode && (
                        <Alert className="relative overflow-hidden bg-white/80 border-2 border-[#8AAEE0] text-[#395886] rounded-3xl shadow-[0_12px_40px_-10px_rgba(57,88,134,0.15)] p-6 backdrop-blur-xl animate-in fade-in slide-in-from-top-4 duration-500">
                            <div className="absolute top-0 left-0 w-2 h-full bg-[#395886] animate-pulse" />
                            <AlertTitle className="font-black text-xl flex items-center gap-2 text-[#395886]">
                                <AlertCircle className="size-6 text-[#628ECB] animate-spin-slow" />
                                Simpan Kode Ini Sekarang
                            </AlertTitle>
                            <AlertDescription className="space-y-4 mt-2">
                                <p className="text-xs md:text-sm text-[#395886]/80 leading-relaxed font-medium">
                                    Berikan kode ini hanya kepada calon admin. Kode <span className="font-bold text-[#395886] bg-[#B1C9EF]/30 px-2 py-0.5 rounded-md border border-[#8AAEE0]/40">tidak akan ditampilkan lagi</span> setelah halaman ini ditutup.
                                </p>
                                <div className="flex items-center gap-3 pt-1">
                                    <code className="bg-white border-2 border-[#8AAEE0] text-[#395886] px-5 py-3 rounded-2xl font-mono text-xl md:text-2xl font-black tracking-widest shadow-inner select-all">
                                        {flash.adminSetupCode}
                                    </code>
                                    <Button
                                        type="button"
                                        size="icon"
                                        onClick={() => handleCopy(flash.adminSetupCode!)}
                                        className="bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl size-12 shadow-md hover:shadow-xl active:scale-90 transition-all duration-300"
                                        title="Salin Kode"
                                    >
                                        {copied ? <Check className="size-5 text-emerald-400" /> : <Copy className="size-5" />}
                                    </Button>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Main Card */}
                    <Card className="group bg-white/80 border border-white/80 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] hover:shadow-[0_15px_35px_rgba(57,88,134,0.15)] transition-all duration-500 overflow-hidden backdrop-blur-xl hover:-translate-y-1">
                        <CardHeader className="border-b border-[#F0F3FA] pb-6">
                            <CardTitle className="text-xl md:text-2xl font-black text-[#395886] tracking-tight">
                                Pendaftaran Akun Admin
                            </CardTitle>
                            <CardDescription className="text-[#395886]/70 text-xs md:text-sm leading-relaxed mt-1 font-normal">
                                Pendaftaran selalu tertutup secara default. Saat dibuka, satu akun baru dapat dibuat melalui halaman setup dengan kode aktivasi khusus.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="pt-6">
                            {setupWindow.isOpen && setupWindow.expiresAt ? (
                                <div className="bg-gradient-to-r from-[#B1C9EF]/30 via-[#D5DEEF]/40 to-white border border-[#8AAEE0]/50 rounded-2xl p-5 flex items-start gap-4 transition-all duration-300 shadow-2xs">
                                    <div className="bg-[#395886] text-white p-3 rounded-xl shrink-0 shadow-md">
                                        <Clock3 className="size-5 animate-spin-slow" />
                                    </div>
                                    <div>
                                        <p className="font-extrabold text-[#395886] text-sm md:text-base">
                                            Pendaftaran Sedang Dibuka
                                        </p>
                                        <p className="text-xs text-[#395886]/80 mt-1 leading-relaxed font-medium">
                                            Otomatis ditutup pada <span className="font-extrabold text-[#395886] underline decoration-[#8AAEE0]">{formatExpiry(setupWindow.expiresAt)}</span>, atau segera setelah satu akun berhasil dibuat.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="bg-[#F0F3FA]/80 text-[#395886]/70 border-2 border-dashed border-[#B1C9EF] rounded-2xl p-6 text-xs md:text-sm text-center font-bold tracking-wide">
                                    Tidak ada pendaftaran admin yang sedang dibuka.
                                </div>
                            )}
                        </CardContent>

                        <CardFooter className="flex flex-wrap justify-end gap-3 border-t border-[#F0F3FA] pt-6 pb-6 px-6 bg-[#F0F3FA]/40">
                            {setupWindow.isOpen && (
                                <Form
                                    method="delete"
                                    {...sikompenForm({ action: disable.url() })}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                            className="border-[#8AAEE0] text-[#395886] hover:bg-rose-600 hover:text-white hover:border-rose-600 rounded-2xl transition-all duration-300 font-bold text-xs px-5 py-2.5 active:scale-95 shadow-2xs"
                                        >
                                            <LockKeyhole className="size-4 mr-2" />
                                            Tutup pendaftaran
                                        </Button>
                                    )}
                                </Form>
                            )}

                            <Form
                                method="post"
                                {...sikompenForm({ action: enable.url() })}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl shadow-md hover:shadow-xl active:scale-95 transition-all duration-300 font-bold text-xs px-6 py-2.5"
                                    >
                                        <UserPlus className="size-4 mr-2 group-hover:scale-110 transition-transform" />
                                        {setupWindow.isOpen
                                            ? 'Buat kode baru'
                                            : 'Buka pendaftaran admin'}
                                    </Button>
                                )}
                            </Form>
                        </CardFooter>
                    </Card>
                </section>
            </main>
        </>
    );
}
