import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function AdminDashboard() {
    const { auth } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Admin Dashboard
                </h2>
            }
        >
            <Head title="Admin Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="space-y-2 p-6 text-gray-900">
                            <p>Authentication foundation is active.</p>
                            <p>Signed in as: {auth.user.name}</p>
                            <p>Role: {auth.user.role}</p>
                            <div className="flex flex-wrap items-center gap-4 pt-2">
                                <Link
                                    href={route('admin.modules.index')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Open Modules
                                </Link>
                                <Link
                                    href={route('admin.lessons.index')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Open Lessons
                                </Link>
                                <Link
                                    href={route('admin.ebooks.index')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Open Ebooks
                                </Link>
                                <Link
                                    href={route('admin.courses.index')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Open Courses
                                </Link>
                                <Link
                                    href={route('admin.access-tiers.index')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Open Access Tiers
                                </Link>
                                <Link
                                    href={route('admin.students.index')}
                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Open Student Management
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
