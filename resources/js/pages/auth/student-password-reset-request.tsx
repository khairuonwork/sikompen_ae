import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Send } from 'lucide-react';
import { storeRequest } from '@/actions/App/Http/Controllers/StudentPasswordResetController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';

export default function StudentPasswordResetRequest(): React.JSX.Element {
    return <><Head title="Lupa Password Mahasiswa" /><main className="flex min-h-screen items-center justify-center bg-[#F0F3FA] p-4 text-[#395886]"><Card className="w-full max-w-md rounded-3xl border-white/80 bg-white/90 shadow-xl"><CardHeader className="text-center"><KeyRound className="mx-auto size-8" /><CardTitle>Reset Password Mahasiswa</CardTitle><p className="text-sm text-[#395886]/70">Masukkan NIM, lalu mintalah kode reset kepada admin jurusan.</p></CardHeader><Form {...storeRequest.form()}>{({ errors, processing }) => <><CardContent><Label htmlFor="nim">NIM</Label><Input id="nim" name="nim" inputMode="numeric" pattern="[0-9]{9,20}" required className="mt-1 rounded-xl" />{errors.nim && <p className="mt-1 text-xs text-rose-600">{errors.nim}</p>}</CardContent><CardFooter className="flex flex-col gap-3"><Button type="submit" disabled={processing} className="w-full rounded-xl bg-[#395886] text-white"><Send className="mr-2 size-4" />Minta kode reset</Button><Link href={home.url()} className="inline-flex items-center gap-1 text-xs font-bold"><ArrowLeft className="size-3" />Kembali</Link></CardFooter></>}</Form></Card></main></>;
}
