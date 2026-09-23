import { Form, Head, Link } from '@inertiajs/react';
import {
    Eye,
    EyeOff,
    KeyRound,
    ShieldAlert,
    UserPlus,
    ArrowLeft,
} from 'lucide-react';
import { useState } from 'react';
import { store } from '@/actions/App/Http/Controllers/KompenResponHubAdminSetupController';
import { Button } from '@/components/ui/button';
import { sikompenForm, sikompenUrl } from '@/lib/sikompen-url';
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
            <Head title="Setup Admin" />

            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886]">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -right-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-40 -left-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="group animate-in fade-in zoom-in-95 relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_10px_35px_rgba(0,0,0,0.06)] backdrop-blur-xl transition-all duration-500 hover:shadow-[0_20px_45px_rgba(57,88,134,0.18)]">
                    <CardHeader className="space-y-3 border-b border-[#F0F3FA] pb-6 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md transition-all duration-300 group-hover:scale-110 group-hover:rotate-6">
                            {setupOpen ? (
                                <UserPlus className="size-7" />
                            ) : (
                                <ShieldAlert className="size-7" />
                            )}
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                System Registration • Onboarding
                            </p>
                            <CardTitle className="text-2xl font-black tracking-tight text-[#395886] md:text-3xl">
                                {setupOpen
                                    ? 'Buat Akun Admin'
                                    : 'Pendaftaran Ditutup'}
                            </CardTitle>
                            <p className="mx-auto mt-1 max-w-xs text-xs leading-relaxed font-normal text-[#395886]/70 md:text-sm">
                                {setupOpen
                                    ? requiresActivationCode
                                        ? 'Masukkan kode aktivasi yang diberikan oleh admin untuk membuat satu akun baru.'
                                        : 'Belum ada admin. Buat akun admin pertama untuk mulai mengelola Kompen Respon Hub.'
                                    : 'Admin dapat membuka pendaftaran satu akun dari halaman pengaturan bila diperlukan.'}
                            </p>
                        </div>
                    </CardHeader>

                    {setupOpen ? (
                        <Form {...sikompenForm(store.form())} resetOnError>
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4.5 pt-6">
                                        {/* Field Email */}
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="email"
                                                className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                            >
                                                Email Admin
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                autoComplete="email"
                                                autoFocus
                                                required
                                                className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 text-sm text-[#395886] shadow-2xs transition-all duration-300 placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                                placeholder="admin@email.com"
                                            />
                                            {errors.email && (
                                                <p className="mt-0.5 text-xs font-bold text-rose-600">
                                                    {errors.email}
                                                </p>
                                            )}
                                        </div>

                                        {/* Field Password */}
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
                                                    autoComplete="new-password"
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 transition-all hover:bg-[#F0F3FA] hover:text-[#395886] active:scale-90"
                                                    onClick={() =>
                                                        setIsPasswordVisible(
                                                            (visible) =>
                                                                !visible,
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
                                            <p className="mt-0.5 text-[10px] leading-tight font-semibold text-[#395886]/60">
                                                Min. 12 karakter (huruf besar,
                                                kecil, angka, & simbol).
                                            </p>
                                            {errors.password && (
                                                <p className="mt-0.5 text-xs font-bold text-rose-600">
                                                    {errors.password}
                                                </p>
                                            )}
                                        </div>

                                        {/* Field Konfirmasi Password */}
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="password_confirmation"
                                                className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                            >
                                                Konfirmasi Password
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
                                                    className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 pr-11 text-sm text-[#395886] shadow-2xs transition-all duration-300 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                                    autoComplete="new-password"
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 transition-all hover:bg-[#F0F3FA] hover:text-[#395886] active:scale-90"
                                                    onClick={() =>
                                                        setIsPasswordConfirmationVisible(
                                                            (visible) =>
                                                                !visible,
                                                        )
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

                                        {/* Field Kode Aktivasi */}
                                        {requiresActivationCode && (
                                            <div className="grid gap-2 pt-1">
                                                <Label
                                                    htmlFor="activation_code"
                                                    className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase"
                                                >
                                                    Kode Aktivasi
                                                </Label>
                                                <Input
                                                    id="activation_code"
                                                    name="activation_code"
                                                    autoComplete="one-time-code"
                                                    required
                                                    className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 font-mono text-sm font-bold tracking-widest text-[#395886] uppercase shadow-2xs transition-all duration-300 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                                    placeholder="KODE-AKTIVASI"
                                                />
                                                {errors.activation_code && (
                                                    <p className="mt-0.5 text-xs font-bold text-rose-600">
                                                        {errors.activation_code}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </CardContent>

                                    <CardFooter className="flex flex-col gap-4 border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 pt-6 pb-6">
                                        <Button
                                            className="w-full rounded-2xl bg-[#395886] py-3 text-sm font-bold text-white shadow-md transition-all duration-300 hover:bg-[#1E293B] hover:shadow-xl active:scale-95"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <KeyRound className="mr-2 size-4" />
                                            {processing
                                                ? 'Membuat akun…'
                                                : 'Buat akun admin'}
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
                    ) : (
                        <CardFooter className="border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 pt-6 pb-6">
                            <Button
                                asChild
                                variant="outline"
                                className="w-full rounded-2xl border-[#8AAEE0] bg-white/80 py-2.5 text-xs font-bold text-[#395886] shadow-2xs transition-all duration-300 hover:border-[#395886] hover:bg-[#395886] hover:text-white active:scale-95"
                            >
                                <Link
                                    href={sikompenUrl(home.url())}
                                    className="flex items-center justify-center gap-2"
                                >
                                    <ArrowLeft className="size-4" />
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
