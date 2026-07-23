import LessonForm from '@/Components/LessonForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateLesson({
    accessTiers,
    modules,
    scoreboards,
    uploadConstraints,
}) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        module_id: '',
        access_tier_ids: [],
        assessment_id: '',
        title: '',
        thumbnail: null,
        workbook: null,
        audio: null,
        lesson_video_id: '',
        content: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.lessons.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Create Lesson" />
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
                            submitLabel="Create Lesson"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
