import { Form, Head } from "@inertiajs/react";
import { Eye, EyeOff, KeyRound, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { update } from "@/actions/App/Http/Controllers/StudentPasswordController";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { sikompenForm } from "@/lib/sikompen-url";

type StudentPasswordProps = {
    nim: string;
    requiresCurrentPassword: boolean;
};

export default function StudentPassword({
    nim,
    requiresCurrentPassword,
}: StudentPasswordProps): React.JSX.Element {
    const [isCurrentPasswordVisible, setIsCurrentPasswordVisible] = useState(false);
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);
    const [isConfirmationVisible, setIsConfirmationVisible] = useState(false);

    return (
        <>
            <Head title="Ganti Password Mahasiswa" />
            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886]">
                <div className="pointer-events-none absolute -top-40 -left-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-40 -right-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_10px_35px_rgba(0,0,0,0.06)] backdrop-blur-xl">
                    <CardHeader className="space-y-3 border-b border-[#F0F3FA] pb-6 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                            <ShieldCheck className="size-7" />
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">Keamanan Akun</p>
                            <CardTitle className="text-2xl font-black tracking-tight text-[#395886] md:text-3xl">Buat Password Baru</CardTitle>
                            <p className="mx-auto mt-1 max-w-sm text-xs leading-relaxed text-[#395886]/70 md:text-sm">
                                {requiresCurrentPassword
                                    ? "Masukkan password saat ini sebelum membuat password baru."
                                    : "Untuk melindungi data Anda, password awal berbasis NIM wajib diganti sebelum melanjutkan."}
                            </p>
                            <p className="mt-3 rounded-xl bg-[#F0F3FA] px-3 py-2 font-mono text-xs font-bold text-[#395886]">NIM: {nim}</p>
                        </div>
                    </CardHeader>
                    <Form {...sikompenForm(update.form())} resetOnError resetOnSuccess>
                        {({ errors, processing }) => (
                            <>
                                <CardContent className="grid gap-5 pt-6">
                                    {requiresCurrentPassword ? (
                                        <div className="grid gap-2">
                                            <Label htmlFor="current_password" className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">Password Saat Ini</Label>
                                            <div className="relative">
                                                <Input id="current_password" name="current_password" type={isCurrentPasswordVisible ? "text" : "password"} autoComplete="current-password" required className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 pr-11 text-sm text-[#395886] shadow-2xs focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]" />
                                                <Button type="button" variant="ghost" size="icon" aria-label={isCurrentPasswordVisible ? "Sembunyikan password saat ini" : "Tampilkan password saat ini"} className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 hover:bg-[#F0F3FA] hover:text-[#395886]" onClick={() => setIsCurrentPasswordVisible((visible) => !visible)}>
                                                    {isCurrentPasswordVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                                </Button>
                                            </div>
                                            {errors.current_password ? <p className="text-xs font-bold text-rose-600">{errors.current_password}</p> : null}
                                        </div>
                                    ) : null}
                                    <div className="grid gap-2">
                                        <Label htmlFor="password" className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">Password Baru</Label>
                                        <div className="relative">
                                            <Input id="password" name="password" type={isPasswordVisible ? "text" : "password"} autoComplete="new-password" required className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 pr-11 text-sm text-[#395886] shadow-2xs focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]" />
                                            <Button type="button" variant="ghost" size="icon" aria-label={isPasswordVisible ? "Sembunyikan password" : "Tampilkan password"} className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 hover:bg-[#F0F3FA] hover:text-[#395886]" onClick={() => setIsPasswordVisible((visible) => !visible)}>
                                                {isPasswordVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                            </Button>
                                        </div>
                                        <p className="text-[10px] font-semibold text-[#395886]/60">Minimal 12 karakter: huruf besar, kecil, angka, dan simbol. Tidak boleh sama dengan NIM.</p>
                                        {errors.password ? <p className="text-xs font-bold text-rose-600">{errors.password}</p> : null}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation" className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">Konfirmasi Password Baru</Label>
                                        <div className="relative">
                                            <Input id="password_confirmation" name="password_confirmation" type={isConfirmationVisible ? "text" : "password"} autoComplete="new-password" required className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 pr-11 text-sm text-[#395886] shadow-2xs focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]" />
                                            <Button type="button" variant="ghost" size="icon" aria-label={isConfirmationVisible ? "Sembunyikan konfirmasi password" : "Tampilkan konfirmasi password"} className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 hover:bg-[#F0F3FA] hover:text-[#395886]" onClick={() => setIsConfirmationVisible((visible) => !visible)}>
                                                {isConfirmationVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 py-6">
                                    <Button type="submit" disabled={processing} className="w-full rounded-2xl bg-[#395886] py-3 text-sm font-bold text-white shadow-md hover:bg-[#1E293B] hover:text-white">
                                        <KeyRound className="mr-2 size-4" />
                                        {processing ? "Menyimpan…" : "Simpan Password Baru"}
                                    </Button>
                                </CardFooter>
                            </>
                        )}
                    </Form>
                </Card>
            </main>
        </>
    );
}
