import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function StudentEbooksIndex({ ebooks }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Ebooks
                </h2>
            }
        >
            <Head title="Ebooks" />
            <div className="py-12">
                <div className="mx-auto grid max-w-5xl gap-6 sm:px-6 lg:px-8 md:grid-cols-2">
                    {ebooks.map((ebook) => (
                        <div
                            key={ebook.id}
                            className="rounded-xl bg-white p-6 shadow-sm"
                        >
                            <h3 className="text-lg font-semibold text-gray-900">
                                {ebook.title}
                            </h3>
                            <a
                                href={ebook.file_url}
                                className="mt-4 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Open Ebook
                            </a>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
