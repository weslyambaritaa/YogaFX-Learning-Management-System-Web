import { Button } from '@/Components/ui/button';
import StudentProgressStudentLayout from '@/Components/admin/student-progress/StudentProgressStudentLayout';
import { router, useForm, usePage } from '@inertiajs/react';

const statusMessages = {
    'student-progress-certificate-generated': 'Certificate has been generated.',
    'student-progress-certificate-recreated': 'Certificate has been recreated.',
    'student-progress-certificate-deleted': 'Certificate has been deleted.',
    'student-progress-graduation-email-sent': 'Graduation email has been sent.',
};

export default function Certificates({
    student,
    certificates,
    certificateTypes,
    certificateEligibility,
    status,
}) {
    const errors = usePage().props.errors;
    const certificateForm = useForm({
        certificate_type: certificateTypes[0]?.value ?? '',
    });

    const recreateCertificate = (certificateId) => {
        router.post(
            route('admin.student-progress.certificates.recreate', {
                student: student.id,
                certificate: certificateId,
            }),
        );
    };

    const deleteCertificate = (certificateId) => {
        if (!window.confirm('Delete this certificate?')) {
            return;
        }

        router.delete(
            route('admin.student-progress.certificates.destroy', {
                student: student.id,
                certificate: certificateId,
            }),
        );
    };

    return (
        <StudentProgressStudentLayout
            title="Certificate"
            description="Generate and manage certificates for this student."
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
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-2">
                        <h3 className="text-base font-semibold text-gray-900">
                            Generate Certificate
                        </h3>
                        <p className="text-sm text-gray-500">
                            Create a new certificate record for the selected certificate
                            type.
                        </p>
                    </div>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            certificateForm.post(
                                route('admin.student-progress.certificates.store', {
                                    student: student.id,
                                }),
                            );
                        }}
                        className="flex w-full flex-col gap-3 lg:max-w-xl lg:flex-row"
                    >
                        <select
                            value={certificateForm.data.certificate_type}
                            onChange={(event) =>
                                certificateForm.setData(
                                    'certificate_type',
                                    event.target.value,
                                )
                            }
                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {certificateTypes.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                        <Button
                            type="submit"
                            disabled={
                                certificateForm.processing ||
                                !certificateEligibility.eligible
                            }
                        >
                            Generate Certificate
                        </Button>
                    </form>
                </div>

                <div className="mt-4">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={certificates.length === 0}
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

                {!certificateEligibility.eligible && certificateEligibility.message && (
                    <div className="mt-4 text-sm text-gray-600">
                        {certificateEligibility.message}
                    </div>
                )}
            </div>

            <div className="rounded-lg bg-white p-6 shadow-sm">
                {certificates.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500">
                        No certificate records are available for this student yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-medium text-gray-700">
                                        Certificate Type
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium text-gray-700">
                                        Version
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
                                {certificates.map((certificate) => (
                                    <tr key={certificate.id}>
                                        <td className="px-4 py-3 font-medium text-gray-900">
                                            {certificate.type_label}
                                        </td>
                                        <td className="px-4 py-3">v{certificate.version}</td>
                                        <td className="px-4 py-3">
                                            {certificate.generated_at}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={!certificateEligibility.eligible}
                                                    onClick={() =>
                                                        recreateCertificate(certificate.id)
                                                    }
                                                >
                                                    Recreate Certificate
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <a href={certificate.download_url}>
                                                        Download Certificate
                                                    </a>
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() =>
                                                        deleteCertificate(certificate.id)
                                                    }
                                                >
                                                    Delete Certificate
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </StudentProgressStudentLayout>
    );
}
