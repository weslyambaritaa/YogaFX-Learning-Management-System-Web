import LessonForm from '@/Components/LessonForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateLesson({ accessTiers, modules }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        module_id: '',
        access_tier_ids: [],
        assessment_id: '',
        title: '',
        thumbnail: null,
        workbook: null,
        video: '',
        audio: '',
        content: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.lessons.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Lesson
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
            <Head title="Create Lesson" />
            <div className="py-12">
                <div className="mx-auto max-w-5xl sm:px-6 lg:px-8">
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
                            onSubmit={submit}
                            submitLabel="Create Lesson"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
