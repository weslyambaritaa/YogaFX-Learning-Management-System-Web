import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function StudentModulesIndex({ modules }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Modules
                </h2>
            }
        >
            <Head title="Modules" />
            <div className="py-12">
                <div className="mx-auto grid max-w-7xl gap-6 sm:px-6 lg:px-8 md:grid-cols-2 xl:grid-cols-3">
                    {modules.map((module) => (
                        <Link
                            key={module.id}
                            href={route('modules.show', module.url_slug)}
                            className="overflow-hidden rounded-xl bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                        >
                            <img
                                src={module.thumbnail_url}
                                alt={module.title}
                                className="h-48 w-full object-cover"
                            />
                            <div className="space-y-2 p-6">
                                <div className="text-xs uppercase tracking-wide text-indigo-500">
                                    Module {module.sort_order}
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900">
                                    {module.title}
                                </h3>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
