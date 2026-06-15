import { Button } from '@/Components/ui/button';
import StudentProgressStudentLayout from '@/Components/admin/student-progress/StudentProgressStudentLayout';
import { router, usePage } from '@inertiajs/react';

const statusMessages = {
    'student-progress-certificate-generated': 'Certificate has been generated.',
    'student-progress-certificate-recreated': 'Certificate has been regenerated.',
    'student-progress-graduation-email-sent': 'Graduation email has been sent.',
};

const badgeClasses = {
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    amber: 'border-amber-200 bg-amber-50 text-amber-800',
    slate: 'border-slate-200 bg-slate-100 text-slate-700',
};

export default function Certificates({
    student,
    certificateRows,
    certificateReadiness,
    status,
}) {
    const errors = usePage().props.errors;
    const generatedRows = certificateRows.filter((row) => row.status === 'Generated');

    const generateCertificate = (certificateType) => {
        router.post(route('admin.student-progress.certificates.store', {
            student: student.id,
        }), {
            certificate_type: certificateType,
        });
    };

    const regenerateCertificate = (certificateId) => {
        router.post(route('admin.student-progress.certificates.recreate', {
            student: student.id,
            certificate: certificateId,
        }));
    };

    return (
        <StudentProgressStudentLayout
            title="Certificate"
            description="Generate and manage certificate PDFs for this student."
            pageTitle="Certificate"
            student={student}
            activeSection="certificate"
        >
            {statusMessages[status] && (
                <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {statusMessages[status]}
                </div>
            )}

            {Object.keys(errors).length > 0 && (
                <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {Object.values(errors)[0]}
                </div>
            )}

            <div className="rounded-lg bg-white p-6 shadow-sm">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-2">
                        <h3 className="text-base font-semibold text-gray-900">
                            Certificate Readiness
                        </h3>
                        <p className="text-sm text-gray-500">
                            Tier mapping and relevant learning completion are checked
                            here before admin generates each certificate PDF manually.
                        </p>
                    </div>

                    <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        <div>
                            Generated: {certificateReadiness.generated_count}/
                            {certificateReadiness.available_count}
                        </div>
                        <div>
                            Tier: {certificateReadiness.tier_name ?? 'No active tier'}
                        </div>
                    </div>
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-3">
                    {certificateReadiness.requirements.map((item) => (
                        <div
                            key={item.key}
                            className="rounded-lg border border-gray-200 px-4 py-3"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-medium text-gray-900">
                                    {item.label}
                                </p>
                                <span className="text-xs text-gray-500">
                                    {item.completed}/{item.total}
                                </span>
                            </div>
                            <p className="mt-2 text-sm text-gray-600">{item.status}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-4 text-sm text-gray-600">
                    {certificateReadiness.message}
                </div>

                {!certificateReadiness.has_required_name && (
                    <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Student primary account name is still empty. Certificate
                        generation will be rejected until that name is filled.
                    </div>
                )}

                <div className="mt-4">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={generatedRows.length === 0}
                        onClick={() =>
                            router.post(
                                route(
                                    'admin.student-progress.certificates.send-graduation-email',
                                    { student: student.id },
                                ),
                            )
                        }
                    >
                        Send Graduation Email
                    </Button>
                </div>
            </div>

            <div className="rounded-lg bg-white p-6 shadow-sm">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-700">
                                    Certificate
                                </th>
                                <th className="px-4 py-3 text-left font-medium text-gray-700">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium text-gray-700">
                                    Generated At
                                </th>
                                <th className="px-4 py-3 text-right font-medium text-gray-700">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {certificateRows.map((row) => (
                                <tr key={row.type}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-gray-900">
                                            {row.label}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500">
                                            {row.detail}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={[
                                                'inline-flex rounded-full border px-3 py-1 text-xs font-medium',
                                                badgeClasses[row.status_tone] ?? badgeClasses.slate,
                                            ].join(' ')}
                                        >
                                            {row.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">
                                        {row.generated_at ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end gap-2">
                                            {row.status === 'Eligible' && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={!row.can_generate}
                                                    onClick={() =>
                                                        generateCertificate(row.type)
                                                    }
                                                >
                                                    Generate
                                                </Button>
                                            )}

                                            {row.status === 'Generated' && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={!row.can_regenerate}
                                                        onClick={() =>
                                                            regenerateCertificate(
                                                                row.certificate_id,
                                                            )
                                                        }
                                                    >
                                                        Regenerate
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <a href={row.download_url}>Download</a>
                                                    </Button>
                                                </>
                                            )}

                                            {row.status === 'Not Eligible' && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled
                                                >
                                                    Not Eligible
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </StudentProgressStudentLayout>
    );
}
