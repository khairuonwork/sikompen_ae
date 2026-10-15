import { Form, Head, Link } from "@inertiajs/react";
import { ArrowLeft, KeyRound, RefreshCw, ShieldCheck, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Pager } from "@/features/kompen-respon-hub/shared/components/pagination";
import type { Pagination } from "@/features/kompen-respon-hub/shared/types";
import { index as adminIndex } from "@/routes/admin/kompen-respon";
import { destroy, refresh } from "@/routes/admin/student-password-resets";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

type StudentPasswordResetRequest = {
    id: number;
    nim: string;
    name: string;
    class: string;
    code: string;
    requestedAt: string;
    expiresAt: string;
    isExpired: boolean;
};

type StudentPasswordResetManagementProps = {
    requests: Pagination<StudentPasswordResetRequest>;
};

function formatDateTime(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return "Jadwal tidak tersedia";
    }

    return `${new Intl.DateTimeFormat("id-ID", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hourCycle: "h23",
        timeZone: "Asia/Jakarta",
    }).format(date)} WIB`;
}

export default function StudentPasswordResetManagement({
    requests,
}: StudentPasswordResetManagementProps): React.JSX.Element {
    const [requestToDelete, setRequestToDelete] = useState<StudentPasswordResetRequest | null>(null);

    return (
        <>
            <Head title="Reset Password Mahasiswa" />

            <main className="relative min-h-screen overflow-x-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886] md:p-8">
                <div className="pointer-events-none absolute -top-40 -left-40 size-[36rem] rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -right-40 -bottom-20 size-[32rem] rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <div className="relative z-10 mx-auto grid w-full max-w-6xl gap-5">
                    <header className="flex flex-col justify-between gap-4 border-b border-[#D5DEEF] pb-5 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                                <KeyRound className="size-6" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                    Administrasi Akun Mahasiswa
                                </p>
                                <h1 className="text-2xl font-black tracking-tight text-[#395886]">
                                    Reset Password Mahasiswa
                                </h1>
                            </div>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-2xl border-[#8AAEE0] bg-white/80 text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white"
                        >
                            <Link href={adminIndex.url()}>
                                <ArrowLeft className="size-4" /> Kembali ke panel
                            </Link>
                        </Button>
                    </header>

                    <Card className="overflow-hidden rounded-3xl border border-white/80 bg-white/85 py-0 shadow-[0_8px_30px_rgb(0,0,0,0.04)] backdrop-blur-xl">
                        <CardHeader className="border-b border-[#F0F3FA] px-5 py-5">
                            <CardTitle className="text-lg font-extrabold text-[#395886]">
                                Permintaan reset aktif
                            </CardTitle>
                            <CardDescription className="text-xs text-[#395886]/65">
                                Berikan kode hanya setelah identitas mahasiswa diverifikasi. Jadwal ditampilkan dalam WIB (Asia/Jakarta); kode berlaku 24 jam dan tidak lagi dapat digunakan setelah reset selesai.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {requests.data.length === 0 ? (
                                <div className="grid place-items-center gap-2 px-5 py-14 text-center">
                                    <ShieldCheck className="size-7 text-[#628ECB]" />
                                    <p className="text-sm font-bold text-[#395886]">
                                        Belum ada permintaan reset mahasiswa.
                                    </p>
                                </div>
                            ) : (
                                <div className="data-table-scroll">
                                    <table className="data-table min-w-[860px]">
                                        <thead className="bg-[#B1C9EF]/20 text-left text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                                            <tr>
                                                <th className="px-5 py-4">Mahasiswa</th>
                                                <th className="px-5 py-4">Kelas</th>
                                                <th className="px-5 py-4">Kode reset</th>
                                                <th className="px-5 py-4">Diminta</th>
                                                <th className="px-5 py-4">Berlaku hingga</th>
                                                <th className="px-5 py-4">Status</th>
                                                <th className="px-5 py-4 text-right">Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#F0F3FA]">
                                            {requests.data.map((request) => (
                                                <tr key={request.id}>
                                                    <td className="px-5 py-4">
                                                        <p className="font-bold text-[#395886]">{request.name}</p>
                                                        <p className="mt-0.5 font-mono text-xs text-[#628ECB]">{request.nim}</p>
                                                    </td>
                                                    <td className="px-5 py-4 text-sm font-medium">{request.class}</td>
                                                    <td className="px-5 py-4">
                                                        <code className="rounded-lg bg-[#F0F3FA] px-2.5 py-1.5 font-mono text-sm font-black tracking-widest text-[#395886]">
                                                            {request.code}
                                                        </code>
                                                    </td>
                                                    <td className="px-5 py-4 text-xs whitespace-nowrap">{formatDateTime(request.requestedAt)}</td>
                                                    <td className="px-5 py-4 text-xs whitespace-nowrap">{formatDateTime(request.expiresAt)}</td>
                                                    <td className="px-5 py-4">
                                                        <span className={request.isExpired ? "rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700" : "rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700"}>
                                                            {request.isExpired ? "Kedaluwarsa" : "Aktif"}
                                                        </span>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <div className="flex min-w-max justify-end gap-2">
                                                            <Form {...refresh.form(request.id)}>
                                                                {({ processing }) => (
                                                                    <Button type="submit" size="sm" variant="outline" disabled={processing} className="rounded-xl border-[#8AAEE0] bg-white text-xs font-bold text-[#395886] hover:bg-[#395886] hover:text-white">
                                                                        <RefreshCw className="size-3.5" />
                                                                        {processing ? "Memperbarui…" : "Kode baru"}
                                                                    </Button>
                                                                )}
                                                            </Form>
                                                            <Button type="button" size="sm" variant="outline" onClick={() => setRequestToDelete(request)} className="rounded-xl border-rose-200 bg-white text-xs font-bold text-rose-700 hover:bg-rose-600 hover:text-white">
                                                                <Trash2 className="size-3.5" /> Hapus
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <Pager data={requests} />
                        </CardContent>
                    </Card>
                </div>
            </main>

            <Dialog open={requestToDelete !== null} onOpenChange={(open) => !open && setRequestToDelete(null)}>
                <DialogContent className="max-w-md rounded-3xl border-[#D5DEEF] bg-white p-0 text-[#395886]">
                    <DialogHeader className="space-y-2 px-6 pt-6">
                        <DialogTitle>Hapus permintaan reset?</DialogTitle>
                        <DialogDescription className="leading-relaxed text-[#395886]/70">
                            Kode reset untuk {requestToDelete?.nim} langsung tidak berlaku. Mahasiswa dapat mengajukan permintaan baru bila masih memerlukannya.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="flex-row justify-end gap-2 border-t border-[#F0F3FA] px-6 py-4 sm:space-x-0">
                        <Button type="button" variant="outline" onClick={() => setRequestToDelete(null)} className="rounded-xl border-[#8AAEE0] text-[#395886]">Batal</Button>
                        {requestToDelete !== null ? (
                            <Form {...destroy.form(requestToDelete.id)} onSuccess={() => setRequestToDelete(null)}>
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing} className="rounded-xl bg-rose-600 text-white hover:bg-rose-700">
                                        <Trash2 className="size-4" /> {processing ? "Menghapus…" : "Hapus permintaan"}
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
