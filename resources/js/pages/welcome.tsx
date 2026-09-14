import { Head, Link } from '@inertiajs/react';
import { GraduationCap, ShieldCheck } from 'lucide-react';
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
            <Head title="Pilih akses" />

            <main className="bg-muted/30 flex min-h-screen items-center justify-center p-4">
                <section className="w-full max-w-3xl space-y-8">
                    <div className="text-center">
                        <p className="text-primary text-sm font-medium">
                            Kompen Respon Hub
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Pilih jenis akses
                        </h1>
                        <p className="text-muted-foreground mt-3 text-sm">
                            Admin mengelola impor dokumen. Mahasiswa dapat
                            melihat dan mengunduh data berdasarkan filter.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-lg">
                                    <ShieldCheck className="size-5" />
                                </div>
                                <CardTitle className="mt-4">Admin</CardTitle>
                                <CardDescription>
                                    Login diperlukan untuk mengunggah workbook
                                    dan mengelola data sumber.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button asChild className="w-full">
                                    <Link
                                        href={
                                            isAdminAuthenticated
                                                ? sikompenUrl(adminIndex.url())
                                                : sikompenUrl(adminLogin.url())
                                        }
                                    >
                                        {isAdminAuthenticated
                                            ? 'Buka panel admin'
                                            : 'Login sebagai admin'}
                                    </Link>
                                </Button>
                                {isInitialAdminSetupAvailable ||
                                isAdminSetupOpen ? (
                                    <Button
                                        asChild
                                        className="mt-3 w-full"
                                        variant="outline"
                                    >
                                        <Link href={sikompenUrl(adminSetup.url())}>
                                            {isInitialAdminSetupAvailable
                                                ? 'Buat akun admin pertama'
                                                : 'Daftar sebagai admin'}
                                        </Link>
                                    </Button>
                                ) : null}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-lg">
                                    <GraduationCap className="size-5" />
                                </div>
                                <CardTitle className="mt-4">
                                    Mahasiswa
                                </CardTitle>
                                <CardDescription>
                                    Akses baca saja untuk melihat Kompen/Respon,
                                    Detail Kompen, dan unduhan XLSX sesuai
                                    periode.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    asChild
                                    className="w-full"
                                    variant="outline"
                                >
                                    <Link href={sikompenUrl(studentIndex.url())}>
                                        Lihat data mahasiswa
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
