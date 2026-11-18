import { storeRequest } from '@/actions/App/Http/Controllers/StudentPasswordResetController';
import { login as studentLogin } from '@/routes/student';
import { PasswordResetRequestForm } from './password-reset-request-form';

type StudentPasswordResetRequestProps = {
    studentNim: string | null;
};

export default function StudentPasswordResetRequest({
    studentNim,
}: StudentPasswordResetRequestProps): React.JSX.Element {
    return <PasswordResetRequestForm title="Reset Password Mahasiswa" description="Masukkan NIM yang terdaftar pada roster mahasiswa aktif, lalu mintalah kode reset kepada admin jurusan." identifierLabel="NIM" identifierName="nim" identifierInputMode="numeric" identifierPattern="[0-9]{9,20}" submitLabel="Minta kode reset" action={storeRequest.form()} loginHref={studentLogin.url()} defaultIdentifier={studentNim} />;
}
