import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function StudentLessonShow({ lesson }) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <div className="text-sm text-gray-500">
                        {lesson.module?.title ?? 'Lesson'}
                    </div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {lesson.title}
                    </h2>
                </div>
            }
        >
            <Head title={lesson.title} />
            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                        <img
                            src={lesson.thumbnail_url}
                            alt={lesson.title}
                            className="h-72 w-full object-cover"
                        />
                        <div className="space-y-4 p-6">
                            <div className="flex flex-wrap items-center gap-3">
                                {lesson.workbook_url && (
                                    <a
                                        href={lesson.workbook_url}
                                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Download Workbook
                                    </a>
                                )}
                                {lesson.video && (
                                    <a
                                        href={lesson.video}
                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Open Video Reference
                                    </a>
                                )}
                                {lesson.audio && (
                                    <a
                                        href={lesson.audio}
                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Open Audio Reference
                                    </a>
                                )}
                            </div>

                            {lesson.content && (
                                <div
                                    className="prose max-w-none text-gray-700"
                                    dangerouslySetInnerHTML={{ __html: lesson.content }}
                                />
                            )}

                            {lesson.assessment_id && (
                                <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                    Assessment relation is prepared for this lesson and
                                    will be activated in a later phase.
                                </div>
                            )}

                            {lesson.module && (
                                <Link
                                    href={route('modules.show', lesson.module.url_slug)}
                                    className="inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    Back to module
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
