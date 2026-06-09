import DeleteConfirmationDialog from '@/Components/DeleteConfirmationDialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function EbooksIndex({ ebooks, status }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            Ebooks
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage downloadable ebook resources per access tier.
                        </p>
                    </div>
                    <Link
                        href={route('admin.ebooks.create')}
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        Create Ebook
                    </Link>
                </div>
            }
        >
            <Head title="Ebooks" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {status === 'ebook-created' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Ebook has been created.
                        </div>
                    )}
                    {status === 'ebook-updated' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Ebook has been updated.
                        </div>
                    )}
                    {status === 'ebook-deleted' && (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Ebook has been deleted.
                        </div>
                    )}
                    <div className="overflow-hidden rounded-lg bg-white shadow-sm">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Title</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Tiers</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Order</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">File</th>
                                        <th className="px-4 py-3 text-left font-medium text-gray-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {ebooks.map((ebook) => (
                                        <tr key={ebook.id}>
                                            <td className="px-4 py-3 font-medium text-gray-900">
                                                {ebook.title}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">
                                                {ebook.access_tiers.join(', ')}
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{ebook.sort_order}</td>
                                            <td className="px-4 py-3">
                                                <a
                                                    href={ebook.preview_url}
                                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                >
                                                    Preview File
                                                </a>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-4">
                                                    <Link
                                                        href={route('admin.ebooks.edit', ebook.id)}
                                                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <DeleteConfirmationDialog
                                                        href={route('admin.ebooks.destroy', ebook.id)}
                                                        title="Delete ebook?"
                                                        description={`This will permanently delete "${ebook.title}". This action cannot be undone.`}
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
