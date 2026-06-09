import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function StudentModuleShow({ module }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    {module.title}
                </h2>
            }
        >
            <Head title={module.title} />
            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                        <img
                            src={module.thumbnail_url}
                            alt={module.title}
                            className="h-72 w-full object-cover"
                        />
                        <div className="p-6">
                            <div className="text-sm uppercase tracking-wide text-indigo-500">
                                Module {module.sort_order}
                            </div>
                            <h3 className="mt-2 text-2xl font-semibold text-gray-900">
                                {module.title}
                            </h3>
                        </div>
                    </div>

                    <div className="space-y-4">
                        {module.lessons.map((lesson) => (
                            <Link
                                key={lesson.id}
                                href={route('lessons.show', lesson.id)}
                                className="block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-md"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="text-xs uppercase tracking-wide text-indigo-500">
                                            Lesson {lesson.sort_order}
                                        </div>
                                        <h4 className="mt-2 text-lg font-semibold text-gray-900">
                                            {lesson.title}
                                        </h4>
                                    </div>
                                    <div className="text-sm text-gray-500">
                                        {[
                                            lesson.has_workbook ? 'Workbook' : null,
                                            lesson.has_video ? 'Video' : null,
                                            lesson.has_audio ? 'Audio' : null,
                                            lesson.has_content ? 'Content' : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' • ')}
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
