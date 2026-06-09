import LessonForm from '@/Components/LessonForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditLesson({ lesson, accessTiers, modules, status }) {
    const { data, setData, patch, processing, errors } = useForm({
        module_id: lesson.module_id ?? '',
        access_tier_id: lesson.access_tier_id ?? '',
        assessment_id: lesson.assessment_id ?? '',
        title: lesson.title ?? '',
        thumbnail: null,
        workbook: null,
        video: lesson.video ?? '',
        audio: lesson.audio ?? '',
        content: lesson.content ?? '',
        sort_order: lesson.sort_order ?? 1,
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.lessons.update', lesson.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Edit Lesson
                    </h2>
                    <Link
                        href={route('admin.lessons.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Lessons
                    </Link>
                </div>
            }
        >
            <Head title="Edit Lesson" />
            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {status === 'lesson-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been created.
                        </div>
                    )}
                    {status === 'lesson-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Lesson has been updated.
                        </div>
                    )}
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <LessonForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            modules={modules}
                            accessTiers={accessTiers}
                            onSubmit={submit}
                            submitLabel="Save Lesson"
                            currentThumbnailUrl={lesson.thumbnail_url}
                            currentWorkbookUrl={lesson.workbook_url}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
