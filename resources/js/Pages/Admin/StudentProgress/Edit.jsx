import StudentProfileForm from '@/Components/StudentProfileForm';
import StudentProgressStudentLayout from '@/Components/admin/student-progress/StudentProgressStudentLayout';
import { router, useForm } from '@inertiajs/react';

export default function EditStudent({ student, accessTiers, status }) {
    const { data, setData, patch, errors, processing } = useForm({
        is_active: Boolean(student.is_active),
        access_tier_id: student.access_tier_id ?? '',
        first_name: student.first_name ?? '',
        last_name: student.last_name ?? '',
        email: student.email ?? '',
        whatsapp: student.whatsapp ?? '',
        preferred_certificate_picture: student.preferred_certificate_picture ?? '',
        profile_photo: student.profile_photo ?? '',
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

    const submit = (event) => {
        event.preventDefault();

        patch(route('admin.student-progress.students.update', student.id));
    };

    const submitStatus = (event) => {
        event.preventDefault();

        router.patch(route('admin.student-progress.students.status', student.id), {
            is_active: data.is_active,
        });
    };

    const resetProgress = () => {
        if (!window.confirm('Delete all learning progress for this student?')) {
            return;
        }

        router.post(route('admin.student-progress.students.reset-progress', student.id));
    };

    const deleteAccount = () => {
        if (!window.confirm('Delete this student account permanently?')) {
            return;
        }

        router.delete(route('admin.student-progress.students.destroy', student.id));
    };

    return (
        <StudentProgressStudentLayout
            title="Student Detail"
            description="Review and update student profile information inside the Student Progress domain."
            pageTitle="Student Detail"
            student={student}
            activeSection="detail"
        >
            <div className="rounded-lg bg-white p-6 shadow-sm">
                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
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
                                    <option key={accessTier.id} value={accessTier.id}>
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

                    <form onSubmit={submitStatus} className="space-y-4">
                        <div>
                            <h3 className="text-lg font-medium text-gray-900">
                                Student Status
                            </h3>
                            <p className="mt-1 text-sm text-gray-600">
                                Active students can access the LMS. Inactive students will be blocked after login.
                            </p>
                        </div>

                        <div>
                            <label
                                htmlFor="is_active"
                                className="text-sm font-medium text-gray-700"
                            >
                                Status
                            </label>
                            <select
                                id="is_active"
                                value={data.is_active ? '1' : '0'}
                                onChange={(event) =>
                                    setData('is_active', event.target.value === '1')
                                }
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            {errors.is_active && (
                                <div className="mt-2 text-sm text-rose-600">
                                    {errors.is_active}
                                </div>
                            )}
                        </div>

                        <button
                            type="submit"
                            className="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
                        >
                            Save Status
                        </button>
                    </form>
                </div>
            </div>

            {status === 'student-profile-updated' && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Student profile has been updated.
                </div>
            )}
            {status === 'student-status-updated' && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Student status has been updated.
                </div>
            )}
            {status === 'student-progress-reset' && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Student learning progress has been deleted.
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

            <div className="rounded-lg border border-rose-200 bg-rose-50 p-6 shadow-sm">
                <div className="space-y-2">
                    <h3 className="text-lg font-medium text-rose-900">
                        Danger Zone
                    </h3>
                    <p className="text-sm text-rose-700">
                        Reset progress removes lesson, assessment, assignment, and certificate records for this student. Delete account removes the student permanently.
                    </p>
                </div>

                <div className="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={resetProgress}
                        className="inline-flex items-center rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-800 hover:bg-rose-100"
                    >
                        Reset Student Progress
                    </button>
                    <button
                        type="button"
                        onClick={deleteAccount}
                        className="inline-flex items-center rounded-md bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800"
                    >
                        Delete Student Account
                    </button>
                </div>
            </div>
        </StudentProgressStudentLayout>
    );
}
