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
    AlertCircle,
    Eye,
    EyeOff,
    KeyRound,
    RotateCcw,
    UserRound,
} from 'lucide-react';
import { storeRequest as storeAdminPasswordResetRequest } from '@/actions/App/Http/Controllers/AdminPasswordResetController';
import {
    updatePassword,
    updateUsername,
} from '@/actions/App/Http/Controllers/AdminCredentialsController';
import {
    disable,
    enable,
} from '@/actions/App/Http/Controllers/KompenResponHubAdminSetupController';
import {
    destroyCode,
    storeCode,
} from '@/actions/App/Http/Controllers/KompenResponHubPasswordResetSettingsController';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as adminIndex } from '@/routes/admin/kompen-respon';

type AdminSettingsProps = {
    currentAdmin: {
        username: string;
    };
    setupWindow: {
        isOpen: boolean;
        expiresAt: string | null;
    };
    flash: {
        adminSetupCode: string | null;
        adminResetCode: string | null;
        success: string | null;
    };
    emergencyAdmins: Array<{ id: number; username: string }>;
    canManageAdminResets: boolean;
    adminPasswordResetRequests: Array<{ id: number; username: string; requestedAt: string | null; expiresAt: string | null; isIssued: boolean }>;
};

function formatExpiry(expiresAt: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'full',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(expiresAt));
}

function PasswordInput({
    id,
    name,
    autoComplete,
    visible,
    onToggle,
}: {
    id: string;
    name: string;
    autoComplete: 'current-password' | 'new-password';
    visible: boolean;
    onToggle: () => void;
}): React.JSX.Element {
    return (
        <div className="relative">
            <Input
                id={id}
                name={name}
                type={visible ? 'text' : 'password'}
                autoComplete={autoComplete}
                required
                className="rounded-xl border-[#8AAEE0] bg-white pr-11 text-[#1E293B]"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={visible ? 'Sembunyikan password' : 'Tampilkan password'}
                onClick={onToggle}
                className="absolute top-1/2 right-1 size-8 -translate-y-1/2 rounded-lg text-[#395886] hover:bg-[#F0F3FA] hover:text-[#1E293B]"
            >
                {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </Button>
        </div>
    );
}

export default function AdminSettings({
    currentAdmin,
    setupWindow,
    flash,
    emergencyAdmins,
    canManageAdminResets,
    adminPasswordResetRequests,
}: AdminSettingsProps): React.JSX.Element {
    const [copied, setCopied] = useState(false);
    const [isUsernamePasswordVisible, setIsUsernamePasswordVisible] = useState(false);
    const [isCurrentPasswordVisible, setIsCurrentPasswordVisible] = useState(false);
    const [isNewPasswordVisible, setIsNewPasswordVisible] = useState(false);
    const [isPasswordConfirmationVisible, setIsPasswordConfirmationVisible] = useState(false);

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
                            <Link href={adminIndex.url()} className="flex items-center gap-2">
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

                    {flash.adminResetCode && (
                        <Alert className="border-[#8AAEE0] bg-white text-[#395886]">
                            <AlertTitle>Kode reset admin — tampilkan sekali</AlertTitle>
                            <AlertDescription className="flex flex-wrap items-center gap-3"><code className="rounded-lg bg-[#F0F3FA] px-3 py-2 font-mono text-lg font-black tracking-widest">{flash.adminResetCode}</code><Button type="button" variant="outline" className="h-9" onClick={() => handleCopy(flash.adminResetCode!)}>Salin kode</Button><span className="text-xs">Berlaku 15 menit.</span></AlertDescription>
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
                                    action={disable.url()}
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
                                action={enable.url()}
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

                    <Card className="overflow-hidden rounded-3xl border border-white/80 bg-white/90 text-[#395886] shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-xl font-black text-[#395886]">Keamanan Akun Saya</CardTitle>
                            <CardDescription className="text-sm font-medium leading-relaxed text-[#395886]/80">Kelola username dan password hanya saat diperlukan. Setiap perubahan mengeluarkan sesi admin lain.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 sm:flex-row">
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline" className="justify-start rounded-2xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]">
                                        <UserRound className="size-4" /> Ganti username
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-3xl border-[#D5DEEF] bg-white p-0 text-[#395886] sm:max-w-md">
                                    <DialogHeader className="border-b border-[#F0F3FA] px-6 pt-6 pb-5 text-left">
                                        <DialogTitle className="text-xl font-black text-[#395886]">Ganti username</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">Gunakan username unik. Konfirmasi password diperlukan sebelum disimpan.</DialogDescription>
                                    </DialogHeader>
                                    <Form {...updateUsername.form()} className="grid gap-4 px-6 py-5">
                                        {({ errors, processing }) => (
                                            <>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="username" className="text-xs font-bold">Username baru</label>
                                                    <Input id="username" name="username" defaultValue={currentAdmin.username} autoComplete="username" required className="rounded-xl border-[#8AAEE0] bg-white" />
                                                    {errors.username ? <p className="text-xs font-semibold text-rose-600">{errors.username}</p> : null}
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="username_current_password" className="text-xs font-bold">Password saat ini</label>
                                                    <PasswordInput id="username_current_password" name="current_password" autoComplete="current-password" visible={isUsernamePasswordVisible} onToggle={() => setIsUsernamePasswordVisible((visible) => !visible)} />
                                                    {errors.current_password ? <p className="text-xs font-semibold text-rose-600">{errors.current_password}</p> : null}
                                                </div>
                                                <DialogFooter className="border-t border-[#F0F3FA] pt-4">
                                                    <DialogClose asChild><Button type="button" variant="outline" className="rounded-xl border-[#8AAEE0] text-[#395886]">Batal</Button></DialogClose>
                                                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-white hover:bg-[#1E293B]">{processing ? 'Menyimpan…' : 'Simpan username'}</Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button className="justify-start rounded-2xl bg-[#395886] text-white hover:bg-[#1E293B]">
                                        <KeyRound className="size-4" /> Ganti password
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-3xl border-[#D5DEEF] bg-white p-0 text-[#395886] sm:max-w-md">
                                    <DialogHeader className="border-b border-[#F0F3FA] px-6 pt-6 pb-5 text-left">
                                        <DialogTitle className="text-xl font-black text-[#395886]">Ganti password</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">Gunakan password baru yang kuat dan jangan gunakan ulang password lama.</DialogDescription>
                                    </DialogHeader>
                                    <Form {...updatePassword.form()} className="grid gap-4 px-6 py-5">
                                        {({ errors, processing }) => (
                                            <>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="password_current_password" className="text-xs font-bold">Password saat ini</label>
                                                    <PasswordInput id="password_current_password" name="current_password" autoComplete="current-password" visible={isCurrentPasswordVisible} onToggle={() => setIsCurrentPasswordVisible((visible) => !visible)} />
                                                    {errors.current_password ? <p className="text-xs font-semibold text-rose-600">{errors.current_password}</p> : null}
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="password" className="text-xs font-bold">Password baru</label>
                                                    <PasswordInput id="password" name="password" autoComplete="new-password" visible={isNewPasswordVisible} onToggle={() => setIsNewPasswordVisible((visible) => !visible)} />
                                                    {errors.password ? <p className="text-xs font-semibold text-rose-600">{errors.password}</p> : null}
                                                </div>
                                                <div className="grid gap-1.5">
                                                    <label htmlFor="password_confirmation" className="text-xs font-bold">Konfirmasi password baru</label>
                                                    <PasswordInput id="password_confirmation" name="password_confirmation" autoComplete="new-password" visible={isPasswordConfirmationVisible} onToggle={() => setIsPasswordConfirmationVisible((visible) => !visible)} />
                                                </div>
                                                <DialogFooter className="border-t border-[#F0F3FA] pt-4">
                                                    <DialogClose asChild><Button type="button" variant="outline" className="rounded-xl border-[#8AAEE0] text-[#395886]">Batal</Button></DialogClose>
                                                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-white hover:bg-[#1E293B]">{processing ? 'Menyimpan…' : 'Simpan password'}</Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline" className="justify-start rounded-2xl border-amber-300 bg-amber-50 text-amber-900 hover:bg-amber-600 hover:text-white">
                                        <RotateCcw className="size-4" /> Minta reset password
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-3xl border-[#D5DEEF] bg-white p-0 text-[#395886] sm:max-w-md">
                                    <DialogHeader className="border-b border-[#F0F3FA] px-6 pt-6 pb-5 text-left">
                                        <DialogTitle className="text-xl font-black text-[#395886]">Minta reset password</DialogTitle>
                                        <DialogDescription className="text-[#395886]/70">Gunakan ini hanya jika password tidak lagi dapat digunakan. Pemegang akun darurat akan menerbitkan kode sekali pakai.</DialogDescription>
                                    </DialogHeader>
                                    <Form {...storeAdminPasswordResetRequest.form()} className="grid gap-4 px-6 py-5">
                                        {({ processing }) => (
                                            <>
                                                <input name="username" type="hidden" value={currentAdmin.username} readOnly />
                                                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-relaxed text-amber-950">
                                                    Permintaan akan dibuat untuk <span className="font-bold">{currentAdmin.username}</span>. Jika password saat ini masih diketahui, pilih <span className="font-bold">Ganti password</span> agar tidak perlu kode reset.
                                                </div>
                                                <DialogFooter className="border-t border-[#F0F3FA] pt-4">
                                                    <DialogClose asChild><Button type="button" variant="outline" className="rounded-xl border-[#8AAEE0] text-[#395886]">Batal</Button></DialogClose>
                                                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-white hover:bg-[#1E293B]">{processing ? 'Mengirim…' : 'Minta kode reset'}</Button>
                                                </DialogFooter>
                                            </>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden rounded-3xl border border-white/80 bg-white/90 text-[#395886] shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-xl font-black text-[#395886]">Akun Darurat Reset Admin</CardTitle>
                            <CardDescription className="text-sm font-medium leading-relaxed text-[#395886]/80">Dua akun ini dapat menerbitkan kode reset. Password akun darurat tidak pernah ditampilkan aplikasi.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-x-auto rounded-xl border border-[#D5DEEF]">
                                <table className="w-full min-w-[480px] text-left text-sm"><thead className="bg-[#D5DEEF]/70 text-xs font-black uppercase tracking-wider text-[#395886]"><tr><th className="p-3">Username</th><th className="p-3">Status</th></tr></thead><tbody>{emergencyAdmins.length === 0 ? <tr><td className="p-3 font-medium text-[#395886]" colSpan={2}>Akun darurat belum di-seed.</td></tr> : emergencyAdmins.map((admin) => <tr key={admin.id} className="border-t border-[#D5DEEF] bg-white"><td className="p-3 font-bold text-[#1E293B]">{admin.username}</td><td className="p-3 font-semibold text-emerald-800">Dapat menerbitkan kode</td></tr>)}</tbody></table>
                            </div>
                            {canManageAdminResets && <div className="overflow-x-auto rounded-xl border border-[#D5DEEF]"><table className="w-full min-w-[700px] text-left text-sm"><thead className="bg-[#D5DEEF]/70 text-xs font-black uppercase tracking-wider text-[#395886]"><tr><th className="p-3">Admin pemohon</th><th className="p-3">Diminta</th><th className="p-3">Status</th><th className="p-3">Tindakan</th></tr></thead><tbody>{adminPasswordResetRequests.length === 0 ? <tr><td className="p-3 font-medium text-[#395886]" colSpan={4}>Belum ada permintaan reset admin.</td></tr> : adminPasswordResetRequests.map((item) => <tr key={item.id} className="border-t border-[#D5DEEF] bg-white text-[#395886]"><td className="p-3 font-bold text-[#1E293B]">{item.username}</td><td className="p-3 font-medium">{item.requestedAt ? formatExpiry(item.requestedAt) : '-'}</td><td className="p-3 font-medium">{item.isIssued && item.expiresAt ? `Kode aktif hingga ${formatExpiry(item.expiresAt)}` : 'Menunggu kode'}</td><td className="flex gap-2 p-3"><Form method="post" action={storeCode.url(item.id)}><Button type="submit" className="h-8 rounded-lg bg-[#395886] text-white">{item.isIssued ? 'Buat ulang' : 'Buat kode'}</Button></Form><Form method="delete" action={destroyCode.url(item.id)}><Button type="submit" variant="outline" className="h-8 rounded-lg border-rose-400 bg-white text-rose-800 hover:bg-rose-700 hover:text-white">Batalkan</Button></Form></td></tr>)}</tbody></table></div>}
                        </CardContent>
                    </Card>

                </section>
            </main>
        </>
    );
}
