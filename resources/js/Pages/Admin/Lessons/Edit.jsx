import LessonForm from '@/Components/LessonForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditLesson({
    lesson,
    accessTiers,
    modules,
    scoreboards,
    uploadConstraints,
    status,
}) {
    const { data, setData, patch, processing, errors, setError, clearErrors } = useForm({
        module_id: lesson.module_id ?? '',
        access_tier_ids: lesson.access_tier_ids ?? [],
        assessment_id: lesson.assessment_id ?? '',
        title: lesson.title ?? '',
        thumbnail: null,
        workbook: null,
        audio: null,
        lesson_video_id: lesson.lesson_video_id ?? '',
        content: lesson.content ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route('admin.lessons.update', lesson.id), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Edit Lesson" />
            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-end">
                        <Link
                            href={route('admin.lessons.index')}
                            className="inline-flex items-center rounded-md border border-gray-900 bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-gray-700"
                        >
                            Back to Lessons
                        </Link>
                    </div>
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
                            setError={setError}
                            clearErrors={clearErrors}
                            errors={errors}
                            processing={processing}
                            modules={modules}
                            accessTiers={accessTiers}
                            scoreboards={scoreboards}
                            uploadConstraints={uploadConstraints}
                            onSubmit={submit}
                            submitLabel="Save Lesson"
                            currentThumbnailUrl={lesson.thumbnail_url}
                            currentWorkbookPreview={lesson.workbook_preview}
                            currentAudioUrl={lesson.audio_preview_url}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
