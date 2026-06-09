import CourseForm from '@/Components/CourseForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateCourse({ accessTiers }) {
    const { data, setData, post, processing, errors, setError, clearErrors } = useForm({
        title: '',
        url_slug: '',
        access_tier_id: '',
        description: '',
        thumbnail: null,
        video: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.courses.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Create Course
                    </h2>
                    <Link
                        href={route('admin.courses.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Courses
                    </Link>
                </div>
            }
        >
            <Head title="Create Course" />
            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <CourseForm
                            data={data}
                            setData={setData}
                            setError={setError}
                            clearErrors={clearErrors}
                            errors={errors}
                            processing={processing}
                            accessTiers={accessTiers}
                            onSubmit={submit}
                            submitLabel="Create Course"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
