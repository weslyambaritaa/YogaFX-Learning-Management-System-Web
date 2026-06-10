import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function StudentCoursesIndex({ courses }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Courses
                </h2>
            }
        >
            <Head title="Courses" />
            <div className="py-12">
                <div className="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8 md:grid-cols-2 xl:grid-cols-3">
                    {courses.map((course) => (
                        <div
                            key={course.id}
                            className="overflow-hidden rounded-xl bg-white shadow-sm"
                        >
                            <img
                                src={course.thumbnail_url}
                                alt={course.title}
                                className="h-48 w-full object-cover"
                            />
                            <div className="space-y-3 p-6">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    {course.title}
                                </h3>
                                <p className="text-sm text-gray-600">
                                    {course.description}
                                </p>
                                <a
                                    href={course.video}
                                    className="inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    Open Course Video
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
