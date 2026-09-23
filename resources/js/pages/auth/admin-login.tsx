import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, ShieldCheck, ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/AdminAuthenticationController';
import { Button } from '@/components/ui/button';
import { sikompenForm, sikompenUrl } from '@/lib/sikompen-url';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
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

export default function AdminLogin({
    flash,
}: AdminLoginProps): React.JSX.Element {
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);

    return (
        <>
            <Head title="Login Admin" />

            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886]">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -left-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -right-40 -bottom-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="group animate-in fade-in zoom-in-95 relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_10px_35px_rgba(0,0,0,0.06)] backdrop-blur-xl transition-all duration-500 hover:shadow-[0_20px_45px_rgba(57,88,134,0.18)]">
                    <CardHeader className="space-y-3 border-b border-[#F0F3FA] pb-6 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md transition-all duration-300 group-hover:scale-110 group-hover:rotate-6">
                            <ShieldCheck className="size-7" />
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                Security Portal • Admin Login
                            </p>
                            <CardTitle className="text-2xl font-black tracking-tight text-[#395886] md:text-3xl">
                                Login Admin
                            </CardTitle>
                            <p className="mx-auto mt-1 max-w-xs text-xs leading-relaxed font-normal text-[#395886]/70 md:text-sm">
                                Akses ini diperlukan untuk mengunggah atau
                                memperbarui dokumen Kompen/Respon.
                            </p>
                        </div>
                    </CardHeader>

                    <Form {...sikompenForm(store.form())} resetOnError>
                        {({ errors, processing }) => (
                            <>
                                <CardContent className="grid gap-5 pt-6">
                                    {flash.success && (
                                        <Alert className="animate-in fade-in slide-in-from-top-2 rounded-2xl border border-emerald-200 bg-emerald-50/90 text-emerald-900 shadow-sm backdrop-blur-md duration-300">
                                            <ShieldCheck className="size-5 animate-bounce text-emerald-600" />
                                            <AlertTitle className="text-sm font-bold text-emerald-950">
                                                Akun Siap Digunakan
                                            </AlertTitle>
                                            <AlertDescription className="text-xs text-emerald-700">
                                                {flash.success}
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {/* Email Field */}
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="email"
                                            className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                        >
                                            Email Address
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autoComplete="email"
                                            required
                                            autoFocus
                                            className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 text-sm text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                            placeholder="nama@email.com"
                                        />
                                        {errors.email && (
                                            <p className="mt-0.5 text-xs font-bold text-rose-600">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    {/* Password Field */}
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                        >
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
                                                className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 pr-11 text-sm text-[#395886] shadow-2xs transition-all duration-300 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                                autoComplete="current-password"
                                                required
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 transition-all hover:bg-[#F0F3FA] hover:text-[#395886] active:scale-90"
                                                onClick={() =>
                                                    setIsPasswordVisible(
                                                        (visible) => !visible,
                                                    )
                                                }
                                            >
                                                {isPasswordVisible ? (
                                                    <EyeOff className="size-4" />
                                                ) : (
                                                    <Eye className="size-4" />
                                                )}
                                            </Button>
                                        </div>
                                        {errors.password && (
                                            <p className="mt-0.5 text-xs font-bold text-rose-600">
                                                {errors.password}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>

                                <CardFooter className="flex flex-col gap-4 border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 pt-6 pb-6">
                                    <Button
                                        className="w-full rounded-2xl bg-[#395886] py-3 text-sm font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:shadow-xl active:scale-95"
                                        type="submit"
                                        disabled={processing}
                                    >
                                        <LockKeyhole className="mr-2 size-4" />
                                        {processing
                                            ? 'Memeriksa…'
                                            : 'Masuk ke panel admin'}
                                    </Button>

                                    <Link
                                        href={sikompenUrl(home.url())}
                                        className="inline-flex items-center justify-center gap-1.5 text-xs font-bold text-[#395886]/70 transition-all duration-200 hover:-translate-x-1 hover:text-[#395886]"
                                    >
                                        <ArrowLeft className="size-3.5" />
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
