import { usePage } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/AdminPasswordResetController';
import { PasswordResetForm } from './password-reset-form';

export default function AdminPasswordReset(): React.JSX.Element {
    const email = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('email') ?? '';
    return <PasswordResetForm title="Buat Password Admin Baru" description="Masukkan kode enam digit dari pemegang akun darurat." identifierLabel="Email admin" identifierName="email" identifierType="email" identifierDefaultValue={email} codeLabel="Kode reset" codeName="otp" action={update.form()} loginHref="/admin/login" />;
}
