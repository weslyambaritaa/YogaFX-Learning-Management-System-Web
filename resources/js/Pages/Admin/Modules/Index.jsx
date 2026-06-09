import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function ModulesIndex({ modules, status }) {
    const errors = usePage().props.errors;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Modules
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage the primary learning containers for YogaFX students.
                        </p>
                    </div>
                    <Link
                        href={route('admin.modules.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Create Module
                    </Link>
                </div>
            }
        >
            <Head title="Modules" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {status === 'module-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been created.
                        </div>
                    )}
                    {status === 'module-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been updated.
                        </div>
                    )}
                    {status === 'module-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Module has been deleted.
                        </div>
                    )}
                    {errors.module && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {errors.module}
                        </div>
                    )}
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Module
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Tiers
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Order
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Lessons
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {modules.map((module) => (
                                        <tr key={module.id}>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <img
                                                        src={module.thumbnail_url}
                                                        alt={module.title}
                                                        className="h-14 w-20 rounded-md object-cover"
                                                    />
                                                    <div>
                                                        <div className="font-medium text-gray-900">
                                                            {module.title}
                                                        </div>
                                                        <div className="text-xs text-gray-500">
                                                            {module.url_slug}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {module.access_tiers.join(', ')}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {module.sort_order}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {module.lessons_count}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <Link
                                                        href={route('admin.modules.edit', module.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <DeleteConfirmationDialog
                                                        href={route('admin.modules.destroy', module.id)}
                                                        title="Delete module?"
                                                        description={`This will permanently delete "${module.title}". This action cannot be undone.`}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
