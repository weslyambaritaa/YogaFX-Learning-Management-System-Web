import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function StudentManagementLayout({
    title,
    description,
    pageTitle,
    student,
    children,
}) {
    return (
        <AuthenticatedLayout
            header={
                <div className="space-y-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 className="text-xl font-semibold leading-tight text-gray-800">
                                {title}
                            </h2>
                            <p className="mt-1 text-sm text-gray-500">{description}</p>
                        </div>

                        <Link
                            href={route('admin.students.index')}
                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Back to Students
                        </Link>
                    </div>

                    {student && (
                        <>
                            <div className="grid gap-4 md:grid-cols-4">
                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">Student</div>
                                    <div className="mt-1 text-lg font-semibold text-gray-900">
                                        {student.name || 'Unnamed student'}
                                    </div>
                                    <div className="mt-2 text-sm text-gray-600">
                                        {student.email}
                                    </div>
                                </div>

                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">Status</div>
                                    <div className="mt-3">
                                        <Badge
                                            variant={
                                                student.is_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {student.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>

                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">Access Tier</div>
                                    <div className="mt-1 text-lg font-semibold text-gray-900">
                                        {student.access_tier?.name || 'Not assigned'}
                                    </div>
                                </div>

                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">Profile Status</div>
                                    <div className="mt-3">
                                        <Badge
                                            variant={
                                                student.profile_is_complete
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {student.profile_is_complete
                                                ? 'Complete'
                                                : 'Incomplete'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">
                                        Total Access Duration
                                    </div>
                                    <div className="mt-1 text-lg font-semibold text-gray-900">
                                        {student.access_time_summary
                                            ?.formatted_total_access_duration
                                            || '00 hours 00 minutes 00 seconds'}
                                    </div>
                                </div>

                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">Last Visit</div>
                                    <div className="mt-1 text-lg font-semibold text-gray-900">
                                        {student.access_time_summary?.last_visit_at
                                            || 'No visit yet'}
                                    </div>
                                </div>

                                <div className="rounded-lg bg-white p-5 shadow-sm">
                                    <div className="text-sm text-gray-500">
                                        Current Session
                                    </div>
                                    <div className="mt-3">
                                        <Badge
                                            variant={
                                                student.access_time_summary
                                                    ?.currently_active
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {student.access_time_summary
                                                ?.currently_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button asChild variant="default" className="w-full sm:w-auto">
                                    <Link href={route('admin.students.edit', student.id)}>
                                        Student Detail
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            }
        >
            <Head title={pageTitle} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
