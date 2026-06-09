import StudentProfileForm from '@/Components/StudentProfileForm';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function EditStudent({ student, accessTiers, status }) {
    const { data, setData, patch, errors, processing } = useForm({
        access_tier_id: student.access_tier_id ?? '',
        first_name: student.first_name ?? '',
        last_name: student.last_name ?? '',
        email: student.email ?? '',
        whatsapp: student.whatsapp ?? '',
        preferred_certificate_picture: student.preferred_certificate_picture ?? '',
        instagram: student.instagram ?? '',
        country: student.country ?? '',
        birth_date: student.birth_date ?? '',
        gender: student.gender ?? '',
        practicing_yoga_for: student.practicing_yoga_for ?? '',
        yoga_sequence_experience: student.yoga_sequence_experience ?? '',
        hours_per_week: student.hours_per_week ?? '',
        current_fitness_level: student.current_fitness_level ?? '',
        flexibility_rating: student.flexibility_rating ?? '',
        motivation: student.motivation ?? '',
        why_yogafx: student.why_yogafx ?? '',
        how_did_you_find_us: student.how_did_you_find_us ?? '',
    });

    const submit = (e) => {
        e.preventDefault();

        patch(route('admin.students.update', student.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Student Detail
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Review and update student profile information.
                        </p>
                    </div>

                    <Link
                        href={route('admin.students.index')}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Back to Students
                    </Link>
                </div>
            }
        >
            <Head title="Student Detail" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid gap-6 md:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">Student</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {student.name || 'Unnamed student'}
                            </div>
                            <div className="mt-2 text-sm text-gray-600">
                                {student.email}
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">
                                Profile Status
                            </div>
                            <div className="mt-2">
                                <span
                                    className={
                                        student.profile_is_complete
                                            ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700'
                                            : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700'
                                    }
                                >
                                    {student.profile_is_complete
                                        ? 'Complete'
                                        : 'Incomplete'}
                                </span>
                            </div>
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="text-sm text-gray-500">Role</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {student.role}
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="mb-4">
                            <h3 className="text-lg font-medium text-gray-900">
                                Access Tier Assignment
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Assign one active learning tier to this student.
                            </p>
                        </div>

                        <div>
                            <label
                                htmlFor="access_tier_id"
                                className="text-sm font-medium text-gray-700"
                            >
                                Access Tier
                            </label>
                            <select
                                id="access_tier_id"
                                value={data.access_tier_id ?? ''}
                                onChange={(event) =>
                                    setData(
                                        'access_tier_id',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Not assigned</option>
                                {accessTiers.map((accessTier) => (
                                    <option
                                        key={accessTier.id}
                                        value={accessTier.id}
                                    >
                                        {accessTier.name}
                                        {!accessTier.is_active ? ' (Inactive)' : ''}
                                    </option>
                                ))}
                            </select>
                            {errors.access_tier_id && (
                                <div className="mt-2 text-sm text-rose-600">
                                    {errors.access_tier_id}
                                </div>
                            )}
                        </div>
                    </div>

                    {status === 'student-profile-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Student profile has been updated.
                        </div>
                    )}

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <StudentProfileForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            processing={processing}
                            onSubmit={submit}
                            submitLabel="Save Student Profile"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
