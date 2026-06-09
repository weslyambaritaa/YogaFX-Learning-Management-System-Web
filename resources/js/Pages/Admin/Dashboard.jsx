import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function AdminDashboard() {
    const { auth } = usePage().props;

    return (
        <AuthenticatedLayout>
            <Head title="Admin Dashboard" />

            <div className="px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-5xl rounded-3xl border border-slate-200 bg-white px-8 py-12 shadow-sm">
                    <div className="space-y-3">
                        <h2 className="text-3xl font-semibold tracking-tight text-slate-900">
                            Hai, {auth.user.name}
                        </h2>
                        <p className="text-base text-slate-600">
                            Welcome to YogaFX Learning Management System
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
