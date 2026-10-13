import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Eye, EyeOff, KeyRound } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ResetFormProps = {
    title: string;
    description: string;
    identifierLabel: string;
    identifierName: 'email' | 'nim';
    identifierType: 'email' | 'text';
    identifierDefaultValue?: string;
    codeLabel: string;
    codeName: 'otp' | 'reset_code';
    action: { action: string; method: 'post' };
    loginHref: string;
};

export function PasswordResetForm({
    title,
    description,
    identifierLabel,
    identifierName,
    identifierType,
    identifierDefaultValue,
    codeLabel,
    codeName,
    action,
    loginHref,
}: ResetFormProps): React.JSX.Element {
    const [visible, setVisible] = useState(false);

    return <><Head title={title} /><main className="flex min-h-screen items-center justify-center bg-[#F0F3FA] p-4 text-[#395886]"><Card className="w-full max-w-md rounded-3xl border-white/80 bg-white/90 shadow-xl"><CardHeader className="text-center"><CardTitle className="text-2xl font-black">{title}</CardTitle><p className="text-sm text-[#395886]/70">{description}</p></CardHeader><Form {...action}>{({ errors, processing }) => <><CardContent className="grid gap-4"><div><Label htmlFor={identifierName}>{identifierLabel}</Label><Input id={identifierName} name={identifierName} type={identifierType} defaultValue={identifierDefaultValue} required className="mt-1 rounded-xl" />{errors[identifierName] && <p className="mt-1 text-xs text-rose-600">{errors[identifierName]}</p>}</div><div><Label htmlFor={codeName}>{codeLabel}</Label><Input id={codeName} name={codeName} required className="mt-1 rounded-xl font-mono tracking-widest" />{errors[codeName] && <p className="mt-1 text-xs text-rose-600">{errors[codeName]}</p>}</div><div><Label htmlFor="password">Password baru</Label><div className="relative mt-1"><Input id="password" name="password" type={visible ? 'text' : 'password'} autoComplete="new-password" required className="rounded-xl pr-10" /><Button type="button" size="icon" variant="ghost" onClick={() => setVisible(!visible)} className="absolute right-1 top-1/2 size-8 -translate-y-1/2">{visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}</Button></div>{errors.password && <p className="mt-1 text-xs text-rose-600">{errors.password}</p>}</div><div><Label htmlFor="password_confirmation">Konfirmasi password baru</Label><Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required className="mt-1 rounded-xl" /></div><p className="text-xs text-[#395886]/65">Minimal 12 karakter: huruf besar, kecil, angka, dan simbol.</p></CardContent><CardFooter className="flex flex-col gap-3"><Button type="submit" disabled={processing} className="w-full rounded-xl bg-[#395886] text-white"><KeyRound className="mr-2 size-4" />{processing ? 'Memproses…' : 'Simpan password baru'}</Button><Link href={loginHref} className="inline-flex items-center gap-1 text-xs font-bold"><ArrowLeft className="size-3" />Kembali ke login</Link></CardFooter></>}</Form></Card></main></>;
}
