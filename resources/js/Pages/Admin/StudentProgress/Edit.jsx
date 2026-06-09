import StudentProfileForm from '@/Components/StudentProfileForm';
import StudentProgressStudentLayout from '@/Components/admin/student-progress/StudentProgressStudentLayout';
import { useForm } from '@inertiajs/react';

export default function EditStudent({ student, accessTiers, status }) {
    const { data, setData, patch, errors, processing } = useForm({
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

    return (
        <StudentProgressStudentLayout
            title="Student Detail"
            description="Review and update student profile information inside the Student Progress domain."
            pageTitle="Student Detail"
            student={student}
            activeSection="detail"
        >
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
        </StudentProgressStudentLayout>
    );
}
