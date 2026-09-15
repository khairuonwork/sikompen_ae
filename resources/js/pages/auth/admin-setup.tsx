import { Form, Head, Link } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, ShieldAlert, UserPlus, ArrowLeft } from 'lucide-react';
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
    const [isPasswordConfirmationVisible, setIsPasswordConfirmationVisible] = useState(false);

    return (
        <>
            <Head title="Setup Admin" />

            <main className="relative min-h-screen bg-[#F0F3FA] text-[#395886] flex items-center justify-center p-4 selection:bg-[#B1C9EF] selection:text-[#395886] overflow-hidden">
                {/* Visual Ambient Background Orbs */}
                <div className="pointer-events-none absolute -top-40 -right-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-40 -left-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="relative z-10 group w-full max-w-md bg-white/80 border border-white/80 rounded-3xl shadow-[0_10px_35px_rgba(0,0,0,0.06)] hover:shadow-[0_20px_45px_rgba(57,88,134,0.18)] backdrop-blur-xl overflow-hidden transition-all duration-500 animate-in fade-in zoom-in-95">
                    <CardHeader className="space-y-3 pb-6 border-b border-[#F0F3FA] text-center">
                        <div className="mx-auto bg-gradient-to-br from-[#395886] to-[#628ECB] text-white flex size-14 items-center justify-center rounded-2xl shadow-md group-hover:scale-110 group-hover:rotate-6 transition-all duration-300">
                            {setupOpen ? (
                                <UserPlus className="size-7" />
                            ) : (
                                <ShieldAlert className="size-7" />
                            )}
                        </div>
                        <div>
                            <p className="text-[10px] font-black uppercase tracking-[0.2em] text-[#628ECB]">
                                System Registration • Onboarding
                            </p>
                            <CardTitle className="text-2xl md:text-3xl font-black tracking-tight text-[#395886]">
                                {setupOpen ? 'Buat Akun Admin' : 'Pendaftaran Ditutup'}
                            </CardTitle>
                            <p className="text-[#395886]/70 text-xs md:text-sm mt-1 leading-relaxed font-normal max-w-xs mx-auto">
                                {setupOpen
                                    ? requiresActivationCode
                                        ? 'Masukkan kode aktivasi yang diberikan oleh admin untuk membuat satu akun baru.'
                                        : 'Belum ada admin. Buat akun admin pertama untuk mulai mengelola Kompen Respon Hub.'
                                    : 'Admin dapat membuka pendaftaran satu akun dari halaman pengaturan bila diperlukan.'}
                            </p>
                        </div>
                    </CardHeader>

                    {setupOpen ? (
                        <Form {...sikompenForm({ action: store.url() })} resetOnError>
                            {({ errors, processing }) => (
                                <>
                                    <CardContent className="grid gap-4.5 pt-6">
                                        {/* Field Email */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="email" className="text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
                                                Email Admin
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                autoComplete="email"
                                                autoFocus
                                                required
                                                className="bg-white/90 border-[#8AAEE0]/70 text-[#395886] rounded-2xl focus-visible:ring-2 focus-visible:ring-[#395886] focus-visible:border-transparent transition-all duration-300 placeholder:text-[#395886]/40 text-sm py-2.5 shadow-2xs"
                                                placeholder="admin@email.com"
                                            />
                                            {errors.email && (
                                                <p className="text-rose-600 text-xs font-bold mt-0.5">
                                                    {errors.email}
                                                </p>
                                            )}
                                        </div>

                                        {/* Field Password */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="password" className="text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
                                                Password
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="password"
                                                    name="password"
                                                    type={isPasswordVisible ? 'text' : 'password'}
                                                    className="bg-white/90 border-[#8AAEE0]/70 text-[#395886] rounded-2xl pr-11 focus-visible:ring-2 focus-visible:ring-[#395886] focus-visible:border-transparent transition-all duration-300 text-sm py-2.5 shadow-2xs"
                                                    autoComplete="new-password"
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="absolute top-1/2 right-1.5 -translate-y-1/2 text-[#395886]/60 hover:text-[#395886] hover:bg-[#F0F3FA] rounded-xl size-8 transition-all active:scale-90"
                                                    onClick={() => setIsPasswordVisible((visible) => !visible)}
                                                >
                                                    {isPasswordVisible ? (
                                                        <EyeOff className="size-4" />
                                                    ) : (
                                                        <Eye className="size-4" />
                                                    )}
                                                </Button>
                                            </div>
                                            <p className="text-[#395886]/60 text-[10px] font-semibold leading-tight mt-0.5">
                                                Min. 12 karakter (huruf besar, kecil, angka, & simbol).
                                            </p>
                                            {errors.password && (
                                                <p className="text-rose-600 text-xs font-bold mt-0.5">
                                                    {errors.password}
                                                </p>
                                            )}
                                        </div>

                                        {/* Field Konfirmasi Password */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation" className="text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
                                                Konfirmasi Password
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    type={isPasswordConfirmationVisible ? 'text' : 'password'}
                                                    className="bg-white/90 border-[#8AAEE0]/70 text-[#395886] rounded-2xl pr-11 focus-visible:ring-2 focus-visible:ring-[#395886] focus-visible:border-transparent transition-all duration-300 text-sm py-2.5 shadow-2xs"
                                                    autoComplete="new-password"
                                                    required
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="absolute top-1/2 right-1.5 -translate-y-1/2 text-[#395886]/60 hover:text-[#395886] hover:bg-[#F0F3FA] rounded-xl size-8 transition-all active:scale-90"
                                                    onClick={() => setIsPasswordConfirmationVisible((visible) => !visible)}
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
                                                <Label htmlFor="activation_code" className="text-[10px] font-black uppercase tracking-[0.15em] text-[#395886]">
                                                    Kode Aktivasi
                                                </Label>
                                                <Input
                                                    id="activation_code"
                                                    name="activation_code"
                                                    autoComplete="one-time-code"
                                                    required
                                                    className="bg-white/90 border-[#8AAEE0]/70 text-[#395886] font-mono tracking-widest rounded-2xl focus-visible:ring-2 focus-visible:ring-[#395886] focus-visible:border-transparent transition-all duration-300 uppercase font-bold text-sm py-2.5 shadow-2xs"
                                                    placeholder="KODE-AKTIVASI"
                                                />
                                                {errors.activation_code && (
                                                    <p className="text-rose-600 text-xs font-bold mt-0.5">
                                                        {errors.activation_code}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </CardContent>

                                    <CardFooter className="flex flex-col gap-4 border-t border-[#F0F3FA] pt-6 pb-6 px-6 bg-[#F0F3FA]/40">
                                        <Button
                                            className="w-full bg-[#395886] hover:bg-[#1E293B] text-white rounded-2xl shadow-md hover:shadow-xl active:scale-95 transition-all duration-300 font-bold text-sm py-3"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <KeyRound className="size-4 mr-2" />
                                            {processing ? 'Membuat akun…' : 'Buat akun admin'}
                                        </Button>
                                        <Link
                                            href={sikompenUrl(home.url())}
                                            className="inline-flex items-center justify-center gap-1.5 text-xs font-bold text-[#395886]/70 hover:text-[#395886] transition-all duration-200 hover:-translate-x-1"
                                        >
                                            <ArrowLeft className="size-3.5" />
                                            Kembali ke pilihan akses
                                        </Link>
                                    </CardFooter>
                                </>
                            )}
                        </Form>
                    ) : (
                        <CardFooter className="border-t border-[#F0F3FA] pt-6 pb-6 px-6 bg-[#F0F3FA]/40">
                            <Button 
                                asChild 
                                variant="outline"
                                className="w-full border-[#8AAEE0] text-[#395886] bg-white/80 hover:bg-[#395886] hover:text-white hover:border-[#395886] transition-all duration-300 rounded-2xl font-bold active:scale-95 py-2.5 text-xs shadow-2xs"
                            >
                                <Link href={sikompenUrl(home.url())} className="flex items-center justify-center gap-2">
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