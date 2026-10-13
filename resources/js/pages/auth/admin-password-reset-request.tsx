import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail, Send } from 'lucide-react';
import { storeRequest } from '@/actions/App/Http/Controllers/AdminPasswordResetController';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { home } from '@/routes';

export default function AdminPasswordResetRequest(): React.JSX.Element {
    return <><Head title="Reset Password Admin" /><main className="flex min-h-screen items-center justify-center bg-[#F0F3FA] p-4 text-[#395886]"><Card className="w-full max-w-md rounded-3xl border-white/80 bg-white/90 shadow-xl"><CardHeader className="text-center"><Mail className="mx-auto size-8" /><CardTitle>Reset Password Admin</CardTitle><p className="text-sm text-[#395886]/70">Masukkan email admin, lalu hubungi pemegang akun darurat untuk memperoleh kode reset.</p></CardHeader><Form {...storeRequest.form()}>{({ errors, processing }) => <><CardContent><Label htmlFor="email">Email admin</Label><Input id="email" name="email" type="email" autoComplete="email" required className="mt-1 rounded-xl" />{errors.email && <p className="mt-1 text-xs text-rose-600">{errors.email}</p>}</CardContent><CardFooter className="flex flex-col gap-3"><Button type="submit" disabled={processing} className="w-full rounded-xl bg-[#395886] text-white"><Send className="mr-2 size-4" />Ajukan reset</Button><Link href={home.url()} className="inline-flex items-center gap-1 text-xs font-bold"><ArrowLeft className="size-3" />Kembali</Link></CardFooter></>}</Form></Card></main></>;
}
