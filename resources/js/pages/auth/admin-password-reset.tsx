import { update } from '@/actions/App/Http/Controllers/AdminPasswordResetController';
import { login as adminLogin } from '@/routes';
import { PasswordResetForm } from './password-reset-form';

export default function AdminPasswordReset(): React.JSX.Element {
    return <PasswordResetForm title="Buat Password Admin Baru" description="Masukkan username dan kode enam digit dari pemegang akun darurat." identifierLabel="Username admin" identifierName="username" codeLabel="Kode reset" codeName="otp" action={update.form()} loginHref={adminLogin.url()} />;
}
