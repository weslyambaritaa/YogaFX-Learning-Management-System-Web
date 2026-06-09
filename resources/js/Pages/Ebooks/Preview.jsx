import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function EbookPreview({ ebook, backUrl, backLabel }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {ebook.title}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Preview the ebook first, then download it only when needed.
                        </p>
                    </div>
                    <Link
                        href={backUrl}
                        className="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        {backLabel}
                    </Link>
                </div>
            }
        >
            <Head title={`${ebook.title} Preview`} />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div>
                            <div className="text-sm font-medium text-slate-900">
                                {ebook.file_name}
                            </div>
                            <div className="mt-1 text-xs text-slate-500">
                                {ebook.mime_type ?? 'Unknown file type'}
                            </div>
                        </div>

                        <Button asChild>
                            <a href={ebook.download_url}>
                                Download Ebook
                            </a>
                        </Button>
                    </div>

                    {ebook.preview_supported ? (
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <iframe
                                src={ebook.preview_url}
                                title={`Preview of ${ebook.title}`}
                                className="h-[75vh] w-full bg-slate-50"
                            />
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                            {ebook.preview_message}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
