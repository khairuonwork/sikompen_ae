import { Form, Head, Link } from "@inertiajs/react";
import { ArrowLeft, Eye, EyeOff, KeyRound, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type ResetFormProps = {
    title: string;
    description: string;
    identifierLabel: string;
    identifierName: "username" | "nim";
    codeLabel: string;
    codeName: "otp" | "reset_code";
    action: { action: string; method: "post" };
    loginHref: string;
};

export function PasswordResetForm({
    title,
    description,
    identifierLabel,
    identifierName,
    codeLabel,
    codeName,
    action,
    loginHref,
}: ResetFormProps): React.JSX.Element {
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);
    const [isConfirmationVisible, setIsConfirmationVisible] = useState(false);

    return (
        <>
            <Head title={title} />

            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886]">
                <div className="pointer-events-none absolute -top-40 -left-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -right-40 -bottom-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="relative z-10 w-full max-w-xl overflow-hidden rounded-3xl border border-white/80 bg-white/95 py-0 text-[#395886] shadow-[0_16px_45px_rgba(57,88,134,0.16)] backdrop-blur-xl">
                    <CardHeader className="gap-3 border-b border-[#F0F3FA] px-6 py-7 text-center">
                        <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                            <ShieldCheck className="size-6" />
                        </div>
                        <div className="space-y-2">
                            <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                Keamanan Akun
                            </p>
                            <CardTitle className="text-2xl font-black tracking-tight text-[#395886]">
                                {title}
                            </CardTitle>
                            <p className="mx-auto max-w-md text-sm leading-relaxed text-[#395886]/75">{description}</p>
                        </div>
                    </CardHeader>

                    <Form {...action} className="grid gap-5 px-6 py-6">
                        {({ errors, processing }) => (
                            <>
                                {Object.keys(errors).length > 0 ? (
                                    <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                        <p className="font-bold">Password belum dapat diubah.</p>
                                        <p className="mt-1 text-xs leading-relaxed">Periksa NIM, kode reset, serta ketentuan password pada kolom yang ditandai. Kode tidak dikonsumsi bila validasi form gagal.</p>
                                    </div>
                                ) : null}
                                <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor={identifierName} className="text-xs font-bold text-[#395886]">
                                            {identifierLabel}
                                        </Label>
                                        <Input id={identifierName} name={identifierName} type="text" autoComplete={identifierName === "username" ? "username" : "off"} maxLength={identifierName === "nim" ? 20 : 64} required className="h-11 rounded-xl border-[#8AAEE0] bg-white text-[#395886]" />
                                        {errors[identifierName] ? <p className="text-xs font-semibold text-rose-600">{errors[identifierName]}</p> : null}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={codeName} className="text-xs font-bold text-[#395886]">
                                            {codeLabel}
                                        </Label>
                                        <Input id={codeName} name={codeName} autoComplete="one-time-code" maxLength={codeName === "reset_code" ? 8 : 6} required className="h-11 rounded-xl border-[#8AAEE0] bg-white font-mono tracking-widest text-[#395886] uppercase" />
                                        {errors[codeName] ? <p className="text-xs font-semibold text-rose-600">{errors[codeName]}</p> : null}
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password" className="text-xs font-bold text-[#395886]">Password baru</Label>
                                    <div className="relative">
                                        <Input id="password" name="password" type={isPasswordVisible ? "text" : "password"} autoComplete="new-password" required className="h-11 rounded-xl border-[#8AAEE0] bg-white pr-11 text-[#395886]" />
                                        <Button type="button" variant="ghost" size="icon" aria-label={isPasswordVisible ? "Sembunyikan password" : "Tampilkan password"} onClick={() => setIsPasswordVisible((visible) => !visible)} className="absolute top-1/2 right-1 size-8 -translate-y-1/2 rounded-lg text-[#395886]/60 hover:bg-[#F0F3FA] hover:text-[#395886]">
                                            {isPasswordVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                        </Button>
                                    </div>
                                    <p className="text-xs leading-relaxed text-[#395886]/65">Minimal 12 karakter: huruf besar, kecil, angka, dan simbol. Password tidak boleh sama dengan NIM.</p>
                                    {errors.password ? <p className="text-xs font-semibold text-rose-600">{errors.password}</p> : null}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation" className="text-xs font-bold text-[#395886]">Konfirmasi password baru</Label>
                                    <div className="relative">
                                        <Input id="password_confirmation" name="password_confirmation" type={isConfirmationVisible ? "text" : "password"} autoComplete="new-password" required className="h-11 rounded-xl border-[#8AAEE0] bg-white pr-11 text-[#395886]" />
                                        <Button type="button" variant="ghost" size="icon" aria-label={isConfirmationVisible ? "Sembunyikan konfirmasi password" : "Tampilkan konfirmasi password"} onClick={() => setIsConfirmationVisible((visible) => !visible)} className="absolute top-1/2 right-1 size-8 -translate-y-1/2 rounded-lg text-[#395886]/60 hover:bg-[#F0F3FA] hover:text-[#395886]">
                                            {isConfirmationVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                        </Button>
                                    </div>
                                </div>

                                <CardFooter className="flex flex-col-reverse gap-3 border-t border-[#F0F3FA] px-0 pt-5 sm:flex-row sm:justify-between">
                                    <Button asChild type="button" variant="outline" className="rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]">
                                        <Link href={loginHref}>
                                            <ArrowLeft className="size-4" /> Kembali ke login
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-white hover:bg-[#1E293B]">
                                        <KeyRound className="size-4" />
                                        {processing ? "Memproses…" : "Simpan password baru"}
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
