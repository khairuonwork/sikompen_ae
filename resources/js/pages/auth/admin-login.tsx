import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/AdminAuthenticationController';
import { Button } from '@/components/ui/button';
import { sikompenForm, sikompenUrl } from '@/lib/sikompen-url';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

type AdminLoginProps = {
    flash: {
        success: string | null;
    };
};

export default function AdminLogin({ flash }: AdminLoginProps): React.JSX.Element {
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);

    return (
        <>
            <Head title="Login admin" />

            <main className="bg-muted/30 flex min-h-screen items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <div className="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-lg">
                            <ShieldCheck className="size-5" />
                        </div>
                        <CardTitle className="mt-4">Login admin</CardTitle>
                        <CardDescription>
                            Akses ini diperlukan untuk mengunggah atau
                            memperbarui dokumen Kompen/Respon.
                        </CardDescription>
                    </CardHeader>
                <Form {...sikompenForm(store.form())} resetOnError>
                        {({ errors, processing }) => (
                            <>
                                <CardContent className="grid gap-4">
                                    {flash.success ? (
                                        <Alert>
                                            <ShieldCheck />
                                            <AlertTitle>Akun siap digunakan</AlertTitle>
                                            <AlertDescription>
                                                {flash.success}
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autoComplete="email"
                                            required
                                            autoFocus
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
                                                autoComplete="current-password"
                                                required
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="absolute top-1/2 right-1 -translate-y-1/2"
                                                onClick={() =>
                                                    setIsPasswordVisible(
                                                        (visible) => !visible,
                                                    )
                                                }
                                                aria-label={
                                                    isPasswordVisible
                                                        ? 'Sembunyikan password'
                                                        : 'Tampilkan password'
                                                }
                                                aria-pressed={isPasswordVisible}
                                            >
                                                {isPasswordVisible ? (
                                                    <EyeOff className="size-4" />
                                                ) : (
                                                    <Eye className="size-4" />
                                                )}
                                            </Button>
                                        </div>
                                        {errors.password ? (
                                            <p className="text-destructive text-sm">
                                                {errors.password}
                                            </p>
                                        ) : null}
                                    </div>
                                </CardContent>
                                <CardFooter className="flex-col gap-3 border-t pt-6">
                                    <Button
                                        className="w-full"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        <LockKeyhole className="size-4" />
                                        {processing
                                            ? 'Memeriksa…'
                                            : 'Masuk ke panel admin'}
                                    </Button>
                                    <Link
                                        href={sikompenUrl(home.url())}
                                        className="text-muted-foreground hover:text-foreground text-sm"
                                    >
                                        Kembali ke pilihan akses
                                    </Link>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>
            </main>
        </>
    );
}
