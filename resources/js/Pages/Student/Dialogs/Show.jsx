import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function Show({ dialog }) {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-[#050505]"
        >
            <Head title={dialog.title} />

            <div className="min-h-screen bg-black py-6">
                <div className="mx-auto max-w-[1600px] px-5 sm:px-8">
                    <Button
                        asChild
                        variant="ghost"
                        className="mb-6 h-auto px-0 text-base font-semibold text-white hover:bg-transparent hover:text-white/82"
                    >
                        <Link href={route('student.dashboard')}>
                            <ArrowLeft className="mr-2 size-4" />
                            back to dashboard
                        </Link>
                    </Button>

                    <h1 className="text-4xl font-semibold tracking-tight text-white">
                        {dialog.title}
                    </h1>

                    <div className="mt-8 bg-white px-6 py-10 text-slate-900 shadow-[0_24px_80px_rgba(0,0,0,0.35)] sm:px-10">
                        {dialog.content ? (
                            <div
                                className="prose prose-lg max-w-none prose-headings:text-[#c10d0d] prose-p:text-slate-900 prose-strong:text-slate-950"
                                dangerouslySetInnerHTML={{ __html: dialog.content }}
                            />
                        ) : (
                            <div className="text-base text-slate-500">
                                No dialog content has been added yet.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
