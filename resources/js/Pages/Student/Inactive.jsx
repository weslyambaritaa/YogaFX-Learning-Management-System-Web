import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function StudentInactive() {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-[#050505]"
        >
            <Head title="Access Blocked" />

            <div className="flex min-h-[70vh] items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(226,72,72,0.16),_transparent_24%),linear-gradient(180deg,#121010_0%,#070707_55%,#030303_100%)] px-4 py-12 sm:px-6 lg:px-8">
                <div className="w-full max-w-2xl rounded-[36px] border border-white/10 bg-white/6 p-8 text-center shadow-[0_24px_90px_rgba(0,0,0,0.4)] backdrop-blur md:p-12">
                    <div className="mx-auto flex size-20 items-center justify-center rounded-full border border-rose-300/20 bg-rose-500/15 text-3xl text-rose-100">
                        !
                    </div>
                    <p className="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-rose-200/90">
                        Access Blocked
                    </p>
                    <h1 className="mt-4 text-3xl font-semibold tracking-tight text-white md:text-4xl">
                        Your student access is currently inactive
                    </h1>
                    <p className="mt-4 text-base leading-8 text-white/72">
                        Please contact the admin for further confirmation.
                    </p>

                    <div className="mt-8 flex justify-center">
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
