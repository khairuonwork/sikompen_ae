import { usePage } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/StudentPasswordResetController';
import { PasswordResetForm } from './password-reset-form';

export default function StudentPasswordReset(): React.JSX.Element {
    const nim = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('nim') ?? '';
    return <PasswordResetForm title="Buat Password Baru" description="Gunakan kode reset dari admin jurusan. Kode hanya berlaku 24 jam." identifierLabel="NIM" identifierName="nim" identifierType="text" identifierDefaultValue={nim} codeLabel="Kode reset" codeName="reset_code" action={update.form()} loginHref="/mahasiswa/login" />;
}
