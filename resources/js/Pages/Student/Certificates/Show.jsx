import { Button } from "@/Components/ui/button";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { Download } from "lucide-react";

export default function StudentCertificateShow({ module, certificate }) {
    const generatedCertificates = certificate?.generated_certificates ?? [];

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={module.title} />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section>
                    <h1 className="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                        {certificate?.title ?? module.title}
                    </h1>
                </section>

                <section className="max-w-3xl">
                    <div className="space-y-6">
                        {generatedCertificates.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between gap-4 rounded-[20px] border border-white/10 bg-white/[0.04] p-6"
                            >
                                <div>
                                    <p className="text-lg font-bold text-white">
                                        {item.type_label}
                                    </p>
                                    <p className="text-sm text-white/60">
                                        Generated {item.generated_at}
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    className="rounded-full bg-white px-6 font-bold text-black hover:bg-white/90"
                                >
                                    <a href={item.download_url}>
                                        <Download className="mr-2 size-4" />
                                        Download
                                    </a>
                                </Button>
                            </div>
                        ))}

                        {!generatedCertificates.length && (
                            <div className="rounded-[20px] border border-white/10 bg-white/[0.04] p-6 text-center text-lg font-bold text-white/60">
                                No certificates generated yet.
                            </div>
                        )}

                        <div className="flex flex-wrap gap-3 pt-4">
                            <Button
                                asChild
                                className="rounded-full bg-[#DB202C] px-8 py-6 text-base font-bold text-white hover:bg-[#c31c28]"
                            >
                                <Link href={route("modules.index")}>
                                    Back to Modules
                                </Link>
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
