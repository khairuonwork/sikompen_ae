import { update } from '@/actions/App/Http/Controllers/StudentPasswordResetController';
import { login as studentLogin } from '@/routes/student';
import { PasswordResetForm } from './password-reset-form';

export default function StudentPasswordReset(): React.JSX.Element {
    return <PasswordResetForm title="Buat Password Baru" description="Gunakan NIM dan kode reset dari admin jurusan. Kode hanya berlaku 24 jam." identifierLabel="NIM" identifierName="nim" codeLabel="Kode reset" codeName="reset_code" action={update.form()} loginHref={studentLogin.url()} />;
}
