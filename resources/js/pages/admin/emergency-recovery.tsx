import { Form, Head } from '@inertiajs/react';
import { Check, Copy, KeyRound, LogOut, ShieldAlert, ShieldCheck, X } from 'lucide-react';
import { toast } from 'sonner';
import { destroy as logout } from '@/actions/App/Http/Controllers/AdminAuthenticationController';
import {
    destroyCode,
    storeCode,
} from '@/actions/App/Http/Controllers/EmergencyAdminRecoveryController';
import { useClipboard } from '@/hooks/use-clipboard';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type AdminResetRequest = {
    id: number;
    username: string;
    requestedAt: string | null;
    expiresAt: string | null;
    isIssued: boolean;
};

type EmergencyAdminRecoveryProps = {
    currentAdmin: {
        username: string;
    };
    resetRequests: AdminResetRequest[];
    flash: {
        adminResetCode: string | null;
        success: string | null;
    };
};

function formatDateTime(value: string | null): string {
    if (value === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(value));
}

export default function EmergencyAdminRecovery({
    currentAdmin,
    resetRequests,
    flash,
}: EmergencyAdminRecoveryProps): React.JSX.Element {
    const [copiedCode, copy] = useClipboard();

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
            <Head title="Pemulihan Akses Admin" />

            <main className="relative min-h-screen overflow-x-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886] md:p-8">
                <div className="pointer-events-none absolute -top-40 -left-40 size-[36rem] rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -right-40 -bottom-20 size-[32rem] rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <div className="relative z-10 mx-auto grid w-full max-w-5xl gap-5">
                    <header className="flex flex-col justify-between gap-4 border-b border-[#D5DEEF] pb-5 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                                <ShieldAlert className="size-6" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#628ECB]">
                                    Mode Pemulihan
                                </p>
                                <h1 className="text-2xl font-black tracking-tight text-[#395886]">
                                    Pemulihan Akses Admin
                                </h1>
                                <p className="mt-1 text-xs font-medium text-[#395886]/70">
                                    Akun darurat: {currentAdmin.username}
                                </p>
                            </div>
                        </div>

                        <Form {...logout.form()}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    className="rounded-2xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:border-rose-600 hover:bg-rose-600 hover:text-white"
                                >
                                    <LogOut className="size-4" />
                                    {processing ? 'Keluar…' : 'Keluar'}
                                </Button>
                            )}
                        </Form>
                    </header>

                    {flash.success ? (
                        <Alert className="border-emerald-200 bg-emerald-50 text-emerald-900">
                            <ShieldCheck className="size-5 text-emerald-600" />
                            <AlertTitle className="font-bold">Tindakan berhasil disimpan</AlertTitle>
                            <AlertDescription className="text-emerald-700">{flash.success}</AlertDescription>
                        </Alert>
                    ) : null}

                    {flash.adminResetCode ? (
                        <Alert className="border-2 border-[#8AAEE0] bg-[#F8FBFF] p-5 text-[#395886] shadow-sm">
                            <AlertTitle className="text-lg font-black text-[#395886]">Kode reset admin — tampilkan sekali</AlertTitle>
                            <AlertDescription className="mt-3 flex flex-wrap items-center gap-3">
                                <code className="rounded-xl bg-[#F0F3FA] px-4 py-2.5 font-mono text-lg font-black tracking-widest text-[#1E293B]">
                                    {flash.adminResetCode}
                                </code>
                                <Button
                                    type="button"
                                    className="h-10 rounded-xl bg-[#395886] px-4 text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white"
                                    onClick={() => void handleCopy(flash.adminResetCode!)}
                                >
                                    {copiedCode === flash.adminResetCode ? <Check className="size-4 text-emerald-300" /> : <Copy className="size-4" />}
                                    {copiedCode === flash.adminResetCode ? 'Tersalin' : 'Salin kode'}
                                </Button>
                                <span className="text-xs font-medium text-[#395886]/75">Berlaku 15 menit.</span>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <Card className="overflow-hidden rounded-3xl border border-white/80 bg-white/90 py-0 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                        <CardHeader className="border-b border-[#F0F3FA] px-5 py-5">
                            <CardTitle className="flex items-center gap-2 text-lg font-extrabold text-[#395886]">
                                <KeyRound className="size-5" /> Permintaan reset admin
                            </CardTitle>
                            <CardDescription className="text-xs leading-relaxed text-[#395886]/70">
                                Halaman ini hanya untuk menerbitkan atau membatalkan kode reset admin biasa. Akun darurat tidak dapat mengubah data Kompen/Respon, mengelola mahasiswa, maupun mengubah kredensialnya sendiri.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {resetRequests.length === 0 ? (
                                <div className="grid place-items-center gap-2 px-5 py-14 text-center">
                                    <ShieldCheck className="size-7 text-[#628ECB]" />
                                    <p className="text-sm font-bold text-[#395886]">Belum ada permintaan reset admin.</p>
                                </div>
                            ) : (
                                <div className="data-table-scroll">
                                    <table className="data-table min-w-[760px]">
                                        <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
                                            <tr>
                                                <th className="px-5 py-4">Admin pemohon</th>
                                                <th className="px-5 py-4">Diminta</th>
                                                <th className="px-5 py-4">Status</th>
                                                <th className="px-5 py-4 text-right">Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#F0F3FA]">
                                            {resetRequests.map((request) => (
                                                <tr key={request.id}>
                                                    <td className="px-5 py-4 font-bold text-[#1E293B]">{request.username}</td>
                                                    <td className="px-5 py-4 text-xs font-medium whitespace-nowrap text-[#395886]">{formatDateTime(request.requestedAt)}</td>
                                                    <td className="px-5 py-4">
                                                        <span className={request.isIssued ? 'rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700' : 'rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800'}>
                                                            {request.isIssued ? `Aktif hingga ${formatDateTime(request.expiresAt)}` : 'Menunggu kode'}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <div className="flex min-w-max justify-end gap-2">
                                                            <Form {...storeCode.form(request.id)}>
                                                                {({ processing }) => (
                                                                    <Button type="submit" size="sm" disabled={processing} className="rounded-xl bg-[#395886] text-xs font-bold text-white hover:bg-[#1E293B] hover:text-white">
                                                                        <KeyRound className="size-3.5" />
                                                                        {processing ? 'Membuat…' : request.isIssued ? 'Buat ulang' : 'Buat kode'}
                                                                    </Button>
                                                                )}
                                                            </Form>
                                                            <Form {...destroyCode.form(request.id)}>
                                                                {({ processing }) => (
                                                                    <Button type="submit" size="sm" variant="outline" disabled={processing} className="rounded-xl border-rose-300 bg-white text-xs font-bold text-rose-700 hover:border-rose-600 hover:bg-rose-600 hover:text-white">
                                                                        <X className="size-3.5" />
                                                                        Batalkan
                                                                    </Button>
                                                                )}
                                                            </Form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
