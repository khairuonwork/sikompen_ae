import { Form, Head, Link } from "@inertiajs/react";
import { ArrowLeft, Eye, EyeOff, GraduationCap, LockKeyhole } from "lucide-react";
import { useState } from "react";
import { store } from "@/actions/App/Http/Controllers/StudentAuthenticationController";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { sikompenForm, sikompenUrl } from "@/lib/sikompen-url";
import { home } from "@/routes";

export default function StudentLogin(): React.JSX.Element {
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);

    return (
        <>
            <Head title="Login Mahasiswa" />

            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886]">
                <div className="pointer-events-none absolute -top-40 -right-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-40 -left-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-[0_10px_35px_rgba(0,0,0,0.06)] backdrop-blur-xl">
                    <CardHeader className="space-y-3 border-b border-[#F0F3FA] pb-6 text-center">
                        <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                            <GraduationCap className="size-7" />
                        </div>
                        <div>
                            <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                Akses Mahasiswa
                            </p>
                            <CardTitle className="text-2xl font-black tracking-tight text-[#395886] md:text-3xl">
                                Login Mahasiswa
                            </CardTitle>
                            <p className="mx-auto mt-1 max-w-sm text-xs leading-relaxed text-[#395886]/70 md:text-sm">
                                Gunakan NIM sebagai nama pengguna. Pada masuk pertama, password awal juga menggunakan NIM dan wajib segera diganti.
                            </p>
                        </div>
                    </CardHeader>

                    <Form {...sikompenForm(store.form())} resetOnError>
                        {({ errors, processing }) => (
                            <>
                                <CardContent className="grid gap-5 pt-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="nim" className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                                            NIM
                                        </Label>
                                        <Input
                                            id="nim"
                                            name="nim"
                                            inputMode="numeric"
                                            autoComplete="username"
                                            autoFocus
                                            required
                                            pattern="[0-9]{9,20}"
                                            maxLength={20}
                                            className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 text-sm text-[#395886] shadow-2xs placeholder:text-[#395886]/40 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                            placeholder="Masukkan NIM"
                                        />
                                        {errors.nim ? <p className="text-xs font-bold text-rose-600">{errors.nim}</p> : null}
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password" className="text-[10px] font-black tracking-[0.15em] text-[#395886] uppercase">
                                            Password
                                        </Label>
                                        <div className="relative">
                                            <Input
                                                id="password"
                                                name="password"
                                                type={isPasswordVisible ? "text" : "password"}
                                                autoComplete="current-password"
                                                required
                                                className="rounded-2xl border-[#8AAEE0]/70 bg-white/90 py-2.5 pr-11 text-sm text-[#395886] shadow-2xs focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-[#395886]"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={isPasswordVisible ? "Sembunyikan password" : "Tampilkan password"}
                                                className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-xl text-[#395886]/60 hover:bg-[#F0F3FA] hover:text-[#395886]"
                                                onClick={() => setIsPasswordVisible((visible) => !visible)}
                                            >
                                                {isPasswordVisible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                            </Button>
                                        </div>
                                        {errors.password ? <p className="text-xs font-bold text-rose-600">{errors.password}</p> : null}
                                    </div>
                                </CardContent>
                                <CardFooter className="flex flex-col gap-4 border-t border-[#F0F3FA] bg-[#F0F3FA]/40 px-6 py-6">
                                    <Button type="submit" disabled={processing} className="w-full rounded-2xl bg-[#395886] py-3 text-sm font-bold text-white shadow-md hover:bg-[#1E293B] hover:text-white">
                                        <LockKeyhole className="mr-2 size-4" />
                                        {processing ? "Memeriksa…" : "Masuk"}
                                    </Button>
                                    <Link href={sikompenUrl(home.url())} className="inline-flex items-center justify-center gap-1.5 text-xs font-bold text-[#395886]/70 hover:text-[#395886]">
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
