import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

export default function StudentInactive() {
    const { auth, supportContact } = usePage().props;
    const accountStatus = auth?.user?.account_status ?? 'inactive';
    const isSuspended = accountStatus === 'suspended';

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-[#050505]"
        >
            <Head title="Access Blocked" />

            <div className="flex min-h-[70vh] items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(226,72,72,0.16),_transparent_24%),linear-gradient(180deg,#121010_0%,#070707_55%,#030303_100%)] px-4 py-12 sm:px-6 lg:px-8">
                <div className="w-full max-w-2xl rounded-[16px] border border-white/10 bg-white/6 p-8 text-center shadow-[0_24px_90px_rgba(0,0,0,0.4)] backdrop-blur md:p-12">
                    <div className="mx-auto flex size-20 items-center justify-center rounded-full border border-rose-300/20 bg-rose-500/15 text-3xl text-rose-100">
                        !
                    </div>
                    <p className="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-rose-200/90">
                        {isSuspended ? 'Account Suspended' : 'Access Blocked'}
                    </p>
                    <h1 className="mt-4 text-3xl font-semibold tracking-tight text-white md:text-4xl">
                        {isSuspended
                            ? 'Your YogaFX account is temporarily suspended'
                            : 'Your student access is currently inactive'}
                    </h1>
                    <p className="mt-4 text-base leading-8 text-white/72">
                        {isSuspended
                            ? 'Dear Student, Please be advised that we have detected irregular activity on the platform and for security purposes, the account is temporarily blocked. Please contact us for more support, information, and assistance. Thank you. YogaFX IT Support.'
                            : 'Please contact the admin for further confirmation.'}
                    </p>

                    <div className="mt-8 flex flex-wrap justify-center gap-3">
                        {supportContact?.whatsapp_url ? (
                            <Button
                                asChild
                                type="button"
                                size="lg"
                                className="bg-[#16a34a] text-white hover:bg-[#15803d]"
                            >
                                <a
                                    href={supportContact.whatsapp_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Chat WhatsApp
                                </a>
                            </Button>
                        ) : null}
                        {supportContact?.email_url ? (
                            <Button
                                asChild
                                type="button"
                                size="lg"
                                className="bg-[#2563eb] text-white hover:bg-[#1d4ed8]"
                            >
                                <a href={supportContact.email_url}>Send Email</a>
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            size="lg"
                            className="bg-[#e24848] text-white hover:bg-[#f05a5a]"
                            onClick={() => router.post(route('logout'))}
                        >
                            Logout
                        </Button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
