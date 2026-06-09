import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const sectionLinks = [
    {
        key: 'detail',
        label: 'Student Detail',
        routeName: 'admin.student-progress.students.edit',
    },
    {
        key: 'completed-lessons',
        label: 'Completed Lesson',
        routeName: 'admin.student-progress.completed-lessons.show',
    },
    {
        key: 'assignment',
        label: 'Assignment',
        routeName: 'admin.student-progress.assignments.show',
    },
    {
        key: 'certificate',
        label: 'Certificate',
        routeName: 'admin.student-progress.certificates.show',
    },
];

export default function StudentProgressStudentLayout({
    title,
    description,
    pageTitle,
    student,
    activeSection,
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
                            href={route('admin.student-progress.index')}
                            className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                        >
                            Back to Student Progress
                        </Link>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm text-gray-500">Student</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {student.name || 'Unnamed student'}
                            </div>
                            <div className="mt-2 text-sm text-gray-600">{student.email}</div>
                        </div>

                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm text-gray-500">Access Tier</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {student.access_tier || 'Not assigned'}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-5 shadow-sm">
                            <div className="text-sm text-gray-500">Profile Status</div>
                            <div className="mt-3">
                                <Badge variant={student.profile_is_complete ? 'secondary' : 'outline'}>
                                    {student.profile_is_complete ? 'Complete' : 'Incomplete'}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        {sectionLinks.map((section) => (
                            <Button
                                key={section.key}
                                asChild
                                variant={activeSection === section.key ? 'default' : 'outline'}
                            >
                                <Link href={route(section.routeName, student.id)}>
                                    {section.label}
                                </Link>
                            </Button>
                        ))}
                    </div>
                </div>
            }
        >
            <Head title={pageTitle} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {children}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
