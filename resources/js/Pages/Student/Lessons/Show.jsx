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

                            {lesson.assessment && (
                                <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <div className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                Assessment
                                            </div>
                                            <div className="mt-1 text-base font-semibold text-slate-900">
                                                {lesson.assessment.title}
                                            </div>
                                            <p className="mt-1 text-sm text-slate-600">
                                                {lesson.assessment.is_unlocked
                                                    ? 'This assessment is ready to start.'
                                                    : 'Assessment unlocks after your lesson watch progress reaches 95%.'}
                                            </p>
                                        </div>

                                        {lesson.assessment.is_unlocked ? (
                                            <Link
                                                href={route('assessments.intro', lesson.id)}
                                                className="inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                                            >
                                                {lesson.assessment.current_attempt_id
                                                    ? 'Resume Assessment'
                                                    : 'Open Assessment'}
                                            </Link>
                                        ) : (
                                            <div className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">
                                                Locked
                                            </div>
                                        )}
                                    </div>
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
