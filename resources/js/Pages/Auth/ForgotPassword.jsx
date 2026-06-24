import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            {status && (
                <div className="mb-4 font-['Montserrat'] text-[14px] font-medium text-green-400">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <TextInput
                    id="email"
                    type="email"
                    name="email"
                    value={data.email}
                    className="mt-1 block w-full rounded-[5px] border-[#DB202C] bg-transparent px-[10px] py-[8px] font-['Montserrat'] text-[14px] font-medium text-white placeholder:text-white/40 focus:border-[#DB202C] focus:ring-[#DB202C]"
                    isFocused={true}
                    onChange={(e) => setData('email', e.target.value)}
                />

                <InputError
                    message={errors.email}
                    className="mt-2 font-['Montserrat'] text-[14px] font-medium"
                />

                <div className="mt-4 flex items-center justify-end">
                    <PrimaryButton className="ms-4" disabled={processing}>
                        Send Reset Link
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}