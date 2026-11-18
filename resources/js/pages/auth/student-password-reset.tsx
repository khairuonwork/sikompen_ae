import { update } from '@/actions/App/Http/Controllers/StudentPasswordResetController';
import { login as studentLogin } from '@/routes/student';
import { PasswordResetForm } from './password-reset-form';

type StudentPasswordResetProps = {
    studentNim: string | null;
};

export default function StudentPasswordReset({
    studentNim,
}: StudentPasswordResetProps): React.JSX.Element {
    return <PasswordResetForm title="Buat Password Baru" description="Gunakan NIM dan kode reset dari admin jurusan. Kode hanya berlaku 24 jam dan hanya dapat digunakan satu kali." identifierLabel="NIM" identifierName="nim" codeLabel="Kode reset" codeName="reset_code" action={update.form()} loginHref={studentLogin.url()} defaultIdentifier={studentNim} />;
}
