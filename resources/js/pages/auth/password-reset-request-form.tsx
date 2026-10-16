import { Form, Head, Link } from "@inertiajs/react";
import { ArrowLeft, KeyRound, Send } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

type PasswordResetRequestFormProps = {
    title: string;
    description: string;
    identifierLabel: string;
    identifierName: "username" | "nim";
    identifierInputMode?: "numeric";
    identifierPattern?: string;
    submitLabel: string;
    action: { action: string; method: "post" };
    loginHref: string;
};

export function PasswordResetRequestForm({
    title,
    description,
    identifierLabel,
    identifierName,
    identifierInputMode,
    identifierPattern,
    submitLabel,
    action,
    loginHref,
}: PasswordResetRequestFormProps): React.JSX.Element {
    return (
        <>
            <Head title={title} />

            <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#F0F3FA] p-4 text-[#395886] selection:bg-[#B1C9EF] selection:text-[#395886]">
                <div className="pointer-events-none absolute -top-40 -left-40 size-96 rounded-full bg-[#8AAEE0]/30 blur-3xl" />
                <div className="pointer-events-none absolute -right-40 -bottom-40 size-96 rounded-full bg-[#B1C9EF]/40 blur-3xl" />

                <Card className="relative z-10 w-full max-w-lg overflow-hidden rounded-3xl border border-white/80 bg-white/95 py-0 text-[#395886] shadow-[0_16px_45px_rgba(57,88,134,0.16)] backdrop-blur-xl">
                    <CardHeader className="gap-3 border-b border-[#F0F3FA] px-6 py-7 text-center">
                        <div className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#395886] to-[#628ECB] text-white shadow-md">
                            <KeyRound className="size-6" />
                        </div>
                        <div className="space-y-2">
                            <p className="text-[10px] font-black tracking-[0.2em] text-[#628ECB] uppercase">
                                Pemulihan Akses
                            </p>
                            <CardTitle className="text-2xl font-black tracking-tight text-[#395886]">
                                {title}
                            </CardTitle>
                            <p className="text-sm leading-relaxed text-[#395886]/75">{description}</p>
                        </div>
                    </CardHeader>

                    <Form {...action} resetOnError className="grid gap-5 px-6 py-6">
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor={identifierName} className="text-xs font-bold text-[#395886]">
                                        {identifierLabel}
                                    </Label>
                                    <Input
                                        id={identifierName}
                                        name={identifierName}
                                        type="text"
                                        inputMode={identifierInputMode}
                                        pattern={identifierPattern}
                                        autoComplete={identifierName === "username" ? "username" : "off"}
                                        required
                                        className="h-11 rounded-xl border-[#8AAEE0] bg-white text-[#395886]"
                                    />
                                    {errors[identifierName] ? (
                                        <p className="text-xs font-semibold text-rose-600">{errors[identifierName]}</p>
                                    ) : null}
                                </div>
                                <p className="rounded-xl bg-[#F0F3FA] px-3 py-2.5 text-xs leading-relaxed text-[#395886]/70">
                                    Permintaan tidak menunjukkan apakah akun ditemukan. Hubungi admin yang berwenang untuk memperoleh kode reset.
                                </p>
                                <CardFooter className="flex flex-col-reverse gap-3 border-t border-[#F0F3FA] px-0 pt-5 sm:flex-row sm:justify-between">
                                    <Button asChild type="button" variant="outline" className="rounded-xl border-[#8AAEE0] bg-white text-[#395886] hover:bg-[#F0F3FA]">
                                        <Link href={loginHref}>
                                            <ArrowLeft className="size-4" /> Kembali ke login
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing} className="rounded-xl bg-[#395886] text-white hover:bg-[#1E293B]">
                                        <Send className="size-4" />
                                        {processing ? "Mengajukan…" : submitLabel}
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
