import React, { useEffect, useState } from 'react';
import { Form, Head, Link } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    Clock3,
    Copy,
    Check,
    LockKeyhole,
    Settings,
    UserPlus,
    ArrowLeft,
    AlertCircle,
    Eye,
    EyeOff,
    KeyRound,
    RotateCcw,
    UserRound,
    BookOpen,
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
import { useClipboard } from '@/hooks/use-clipboard';
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
import { admin as adminGuide } from '@/routes/admin/guides';

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
}: AdminSettingsProps): React.JSX.Element {
    const [copiedCode, copy] = useClipboard();
    const [isUsernamePasswordVisible, setIsUsernamePasswordVisible] = useState(false);
    const [isCurrentPasswordVisible, setIsCurrentPasswordVisible] = useState(false);
    const [isNewPasswordVisible, setIsNewPasswordVisible] = useState(false);
    const [isPasswordConfirmationVisible, setIsPasswordConfirmationVisible] = useState(false);
    const [isUsernameDialogOpen, setIsUsernameDialogOpen] = useState(false);

    useEffect(() => {
        if (flash.success === null) {
            return;
        }

        toast.success('Pengaturan berhasil diperbarui', {
            description: flash.success,
            closeButton: true,
            duration: 7000,
        });
    }, [flash.success]);

    const handleCopy = async (code: string): Promise<void> => {
        if (await copy(code)) {
            toast.success('Kode reset disalin', {
                description: 'Simpan kode ini dengan aman. Kode hanya berlaku 15 menit.',
            });

            return;
        }

        toast.error('Kode belum dapat disalin', {
            description: 'Salin kode secara manual lalu coba lagi.',
        });
    };

    return (
        <>
            <Head title="Pengaturan Admin" />

            <main className="min-h-screen bg-[#F0F3FA] px-4 py-6 text-[#395886] md:px-8">
                <section className="mx-auto grid w-full max-w-4xl gap-4">
                    <header className="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-white/80 bg-white/90 p-5 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="bg-[#395886] text-white flex size-14 items-center justify-center rounded-2xl shadow-md transition-colors">
                                <Settings className="size-7" />
                            </div>
                            <div>
                                <h1 className="text-2xl md:text-3xl font-black tracking-tight text-[#395886]">
                                    Pengaturan Admin
                                </h1>
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                asChild
                                variant="outline"
                                className="border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-colors rounded-2xl font-bold text-xs px-4 py-2.5 shadow-2xs"
                            >
                                <a href={adminGuide.url()} target="_blank" rel="noreferrer" className="flex items-center gap-2">
                                    <BookOpen className="size-4" />
                                    Panduan admin
                                </a>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                className="border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-colors rounded-2xl font-bold text-xs px-4 py-2.5 shadow-2xs"
                            >
                                <Link href={adminIndex.url()} className="flex items-center gap-2">
                                    <ArrowLeft className="size-4" />
                                    Kembali ke panel
                                </Link>
                            </Button>
                        </div>
                    </header>

                    {/* Alert Setup Code */}
                    {flash.adminSetupCode && (
                        <Alert className="relative rounded-3xl border border-[#8AAEE0] bg-white p-5 text-[#395886] shadow-sm">
                            <AlertTitle className="font-black text-xl flex items-center gap-2 text-[#395886]">
                                <AlertCircle className="size-6 text-[#628ECB]" />
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
                                        onClick={() => void handleCopy(flash.adminSetupCode!)}
                                        className="bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl size-12 shadow-md transition-colors"
                                        title="Salin Kode"
                                    >
                                        {copiedCode === flash.adminSetupCode ? <Check className="size-5 text-emerald-400" /> : <Copy className="size-5" />}
                                    </Button>
                                </div>
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Main Card */}
                    <Card className="overflow-hidden rounded-3xl border border-white/80 bg-white/90 text-[#395886] shadow-sm">
                        <CardHeader className="border-b border-[#F0F3FA] pb-6">
                            <CardTitle className="text-xl md:text-2xl font-black text-[#395886] tracking-tight">
                                Pendaftaran Akun Admin
                            </CardTitle>
                            <CardDescription className="text-[#395886]/70 text-xs md:text-sm leading-relaxed mt-1 font-normal">
                                Pendaftaran ditutup secara bawaan. Saat dibuka, satu akun baru dapat dibuat melalui halaman pendaftaran dengan kode aktivasi khusus.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="pt-6">
                            {setupWindow.isOpen && setupWindow.expiresAt ? (
                                <div className="bg-[#F0F3FA] border border-[#8AAEE0]/50 rounded-2xl p-5 flex items-start gap-4 transition-colors shadow-2xs">
                                    <div className="bg-[#395886] text-white p-3 rounded-xl shrink-0 shadow-md">
                                        <Clock3 className="size-5" />
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
                                            className="border-[#8AAEE0] bg-white text-[#395886] hover:bg-rose-600 hover:text-white hover:border-rose-600 rounded-2xl transition-colors font-bold text-xs px-5 py-2.5 shadow-2xs"
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
                                        className="bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl shadow-md transition-colors font-bold text-xs px-6 py-2.5"
                                    >
                                        <UserPlus className="size-4 mr-2" />
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
                            <Dialog open={isUsernameDialogOpen} onOpenChange={setIsUsernameDialogOpen}>
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
                                    <Form
                                        {...updateUsername.form()}
                                        className="grid gap-4 px-6 py-5"
                                        onSuccess={() => setIsUsernameDialogOpen(false)}
                                    >
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
                                                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-white hover:bg-[#1E293B] hover:text-white">{processing ? 'Mengirim…' : 'Minta kode reset'}</Button>
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
                            <CardDescription className="text-sm font-medium leading-relaxed text-[#395886]/80">Akun darurat hanya mengelola permintaan reset admin dan tidak dapat membuka data operasional. Password tidak pernah ditampilkan aplikasi.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="overflow-x-auto rounded-xl border border-[#D5DEEF]">
                                <table className="w-full min-w-[480px] text-left text-sm"><thead className="bg-[#D5DEEF]/70 text-xs font-black uppercase tracking-wider text-[#395886]"><tr><th className="p-3">Username</th><th className="p-3">Status</th></tr></thead><tbody>{emergencyAdmins.length === 0 ? <tr><td className="p-3 font-medium text-[#395886]" colSpan={2}>Belum ada akun darurat yang disiapkan.</td></tr> : emergencyAdmins.map((admin) => <tr key={admin.id} className="border-t border-[#D5DEEF] bg-white"><td className="p-3 font-bold text-[#1E293B]">{admin.username}</td><td className="p-3 font-semibold text-emerald-800">Dapat menerbitkan kode</td></tr>)}</tbody></table>
                            </div>
                        </CardContent>
                    </Card>

                </section>
            </main>
        </>
    );
}
