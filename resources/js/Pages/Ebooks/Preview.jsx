import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function EbookPreview({ ebook }) {
    return (
        <AuthenticatedLayout>
            <Head title={`${ebook.title} Preview`} />

            <div className="py-12">
                <div className="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap items-center justify-between gap-4 rounded-[12px] border border-slate-200 bg-white p-5 shadow-sm">
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
                                Access
                            </a>
                        </Button>
                    </div>

                    {ebook.preview_supported ? (
                        <div className="overflow-hidden rounded-[12px] border border-slate-200 bg-white shadow-sm">
                            <iframe
                                src={ebook.preview_url}
                                title={`Preview of ${ebook.title}`}
                                className="h-[75vh] w-full bg-slate-50"
                            />
                        </div>
                    ) : (
                        <div className="rounded-[12px] border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900">
                            {ebook.preview_message}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
