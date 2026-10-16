import { storeRequest } from '@/actions/App/Http/Controllers/AdminPasswordResetController';
import { login as adminLogin } from '@/routes';
import { PasswordResetRequestForm } from './password-reset-request-form';

export default function AdminPasswordResetRequest(): React.JSX.Element {
    return <PasswordResetRequestForm title="Reset Password Admin" description="Masukkan username admin, lalu hubungi pemegang akun darurat untuk memperoleh kode reset." identifierLabel="Username admin" identifierName="username" submitLabel="Ajukan reset" action={storeRequest.form()} loginHref={adminLogin.url()} />;
}
