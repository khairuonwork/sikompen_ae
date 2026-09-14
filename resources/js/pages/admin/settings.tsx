import { Form, Head, Link } from '@inertiajs/react';
import { Clock3, Copy, LockKeyhole, Settings, UserPlus } from 'lucide-react';
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
    return (
        <>
            <Head title="Pengaturan admin" />

            <main className="bg-muted/30 min-h-screen p-4 md:p-8">
                <section className="mx-auto w-full max-w-3xl space-y-6">
                    <header className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-lg">
                                <Settings className="size-5" />
                            </div>
                            <div>
                                <p className="text-primary text-sm font-medium">
                                    Panel admin
                                </p>
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    Pengaturan admin
                                </h1>
                            </div>
                        </div>
                        <Button asChild variant="outline">
                            <Link href={sikompenUrl(adminIndex.url())}>
                                Kembali ke panel
                            </Link>
                        </Button>
                    </header>

                    {flash.success ? (
                        <Alert>
                            <LockKeyhole />
                            <AlertTitle>Pengaturan diperbarui</AlertTitle>
                            <AlertDescription>{flash.success}</AlertDescription>
                        </Alert>
                    ) : null}

                    {flash.adminSetupCode ? (
                        <Alert>
                            <Copy />
                            <AlertTitle>Simpan kode ini sekarang</AlertTitle>
                            <AlertDescription className="space-y-3">
                                <p>
                                    Berikan kode ini hanya kepada calon admin.
                                    Kode tidak akan ditampilkan lagi setelah
                                    halaman ini ditutup atau dimuat ulang.
                                </p>
                                <code className="bg-background block w-fit rounded border px-3 py-2 font-mono text-base tracking-wider">
                                    {flash.adminSetupCode}
                                </code>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <Card>
                        <CardHeader>
                            <CardTitle>Pendaftaran akun admin</CardTitle>
                            <CardDescription>
                                Pendaftaran selalu tertutup secara default.
                                Saat dibuka, satu akun baru dapat dibuat melalui
                                halaman setup dengan kode aktivasi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {setupWindow.isOpen && setupWindow.expiresAt ? (
                                <div className="bg-muted/50 flex gap-3 rounded-lg border p-4 text-sm">
                                    <Clock3 className="text-primary mt-0.5 size-5 shrink-0" />
                                    <div>
                                        <p className="font-medium">
                                            Pendaftaran sedang dibuka
                                        </p>
                                        <p className="text-muted-foreground mt-1">
                                            Otomatis ditutup pada{' '}
                                            {formatExpiry(setupWindow.expiresAt)}
                                            , atau segera setelah satu akun
                                            berhasil dibuat.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                                    Tidak ada pendaftaran admin yang sedang
                                    dibuka.
                                </div>
                            )}
                        </CardContent>
                        <CardFooter className="flex flex-wrap justify-end gap-3 border-t pt-6">
                            {setupWindow.isOpen ? (
                                <Form {...sikompenForm(disable.form())}>
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            <LockKeyhole className="size-4" />
                                            Tutup pendaftaran
                                        </Button>
                                    )}
                                </Form>
                            ) : null}
                            <Form {...sikompenForm(enable.form())}>
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        <UserPlus className="size-4" />
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
