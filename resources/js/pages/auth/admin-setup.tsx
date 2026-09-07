import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, ShieldCheck, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/KompenResponHubAdminSetupController';
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
import { Label } from '@/components/ui/label';
import { home } from '@/routes';

type AdminSetupProps = {
    setupOpen: boolean;
    requiresActivationCode: boolean;
};

export default function AdminSetup({
    setupOpen,
    requiresActivationCode,
}: AdminSetupProps): React.JSX.Element {
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);
    const [isPasswordConfirmationVisible, setIsPasswordConfirmationVisible] =
        useState(false);

    return (
        <>
            <Head title="Setup admin" />

            <main className="bg-muted/30 flex min-h-screen items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-lg">
                            {setupOpen ? (
                                <UserPlus className="size-5" />
                            ) : (
                                <ShieldCheck className="size-5" />
                            )}
                        </div>
                        <CardTitle className="mt-4">
                            {setupOpen
                                ? 'Buat akun admin'
                                : 'Pendaftaran admin ditutup'}
                        </CardTitle>
                        <CardDescription>
                            {setupOpen
                                ? requiresActivationCode
                                    ? 'Masukkan kode aktivasi yang diberikan oleh admin untuk membuat satu akun baru.'
                                    : 'Belum ada admin. Buat akun admin pertama untuk mulai mengelola Kompen Respon Hub.'
                                : 'Admin dapat membuka pendaftaran satu akun dari halaman pengaturan bila diperlukan.'}
                        </CardDescription>
                    </CardHeader>

                    {setupOpen ? (
                        <Form {...store.form()} resetOnError>
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">Email admin</Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                autoComplete="email"
                                                autoFocus
                                                required
                                            />
                                            {errors.email ? (
                                                <p className="text-destructive text-sm">
                                                    {errors.email}
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                Password
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="password"
                                                    name="password"
                                                    type={
                                                        isPasswordVisible
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    className="pr-11"
                                                    autoComplete="new-password"
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="absolute top-1/2 right-1 -translate-y-1/2"
                                                    onClick={() =>
                                                        setIsPasswordVisible(
                                                            (visible) =>
                                                                !visible,
                                                        )
                                                    }
                                                    aria-label={
                                                        isPasswordVisible
                                                            ? 'Sembunyikan password'
                                                            : 'Tampilkan password'
                                                    }
                                                    aria-pressed={
                                                        isPasswordVisible
                                                    }
                                                >
                                                    {isPasswordVisible ? (
                                                        <EyeOff className="size-4" />
                                                    ) : (
                                                        <Eye className="size-4" />
                                                    )}
                                                </Button>
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                Minimal 12 karakter, dengan huruf
                                                besar, huruf kecil, angka, dan
                                                simbol.
                                            </p>
                                            {errors.password ? (
                                                <p className="text-destructive text-sm">
                                                    {errors.password}
                                                </p>
                                            ) : null}
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation">
                                                Konfirmasi password
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    type={
                                                        isPasswordConfirmationVisible
                                                            ? 'text'
                                                            : 'password'
                                                    }
                                                    className="pr-11"
                                                    autoComplete="new-password"
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="absolute top-1/2 right-1 -translate-y-1/2"
                                                    onClick={() =>
                                                        setIsPasswordConfirmationVisible(
                                                            (visible) =>
                                                                !visible,
                                                        )
                                                    }
                                                    aria-label={
                                                        isPasswordConfirmationVisible
                                                            ? 'Sembunyikan konfirmasi password'
                                                            : 'Tampilkan konfirmasi password'
                                                    }
                                                    aria-pressed={
                                                        isPasswordConfirmationVisible
                                                    }
                                                >
                                                    {isPasswordConfirmationVisible ? (
                                                        <EyeOff className="size-4" />
                                                    ) : (
                                                        <Eye className="size-4" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                        {requiresActivationCode ? (
                                            <div className="grid gap-2">
                                                <Label htmlFor="activation_code">
                                                    Kode aktivasi
                                                </Label>
                                                <Input
                                                    id="activation_code"
                                                    name="activation_code"
                                                    autoComplete="one-time-code"
                                                    required
                                                />
                                                {errors.activation_code ? (
                                                    <p className="text-destructive text-sm">
                                                        {errors.activation_code}
                                                    </p>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </CardContent>
                                    <CardFooter className="flex-col gap-3 border-t pt-6">
                                        <Button
                                            className="w-full"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <KeyRound className="size-4" />
                                            {processing
                                                ? 'Membuat akun…'
                                                : 'Buat akun admin'}
                                        </Button>
                                        <Link
                                            href={home.url()}
                                            className="text-muted-foreground hover:text-foreground text-sm"
                                        >
                                            Kembali ke pilihan akses
                                        </Link>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    ) : (
                        <CardFooter className="border-t pt-6">
                            <Button asChild className="w-full" variant="outline">
                                <Link href={home.url()}>
                                    Kembali ke pilihan akses
                                </Link>
                            </Button>
                        </CardFooter>
                    )}
                </Card>
            </main>
        </>
    );
}
