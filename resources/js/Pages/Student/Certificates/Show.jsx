import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Award, ChevronRight, Download, Lock } from 'lucide-react';

export default function StudentCertificateShow({ module, certificate }) {
    const isDownloadReady = certificate?.state === 'download_available' || certificate?.state === 'downloaded';
    const generatedCertificates = certificate?.generated_certificates ?? [];

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title={module.title} />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section className="rounded-[24px] border border-white/10 bg-[#120f0f] p-6 shadow-[0_24px_90px_rgba(0,0,0,0.35)] sm:p-8">
                    <div className="max-w-3xl space-y-3">
                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            Certificate Status
                        </p>
                        <h1 className="text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">
                            {certificate?.title ?? module.title}
                        </h1>
                        <p className="text-sm leading-7 text-white/68">
                            {isDownloadReady
                                ? 'All generated certificates for your account are available here.'
                                : 'This module becomes your certificate library as soon as certificate files are generated.'}
                        </p>
                    </div>
                </section>

                <section className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                        <div className="flex h-full flex-col gap-6 rounded-[22px] border border-white/8 bg-black/20 p-5">
                            <div className="space-y-3">
                                <p className="text-xs uppercase tracking-[0.22em] text-white/45">
                                    Certificate Status
                                </p>
                                <h2 className="text-2xl font-semibold tracking-tight text-white">
                                    {isDownloadReady
                                        ? 'Open your latest certificate file'
                                        : 'Certificate file is not available yet'}
                                </h2>
                                <p className="text-sm leading-7 text-white/60">
                                    {certificate?.support_note ?? 'Every generated certificate tied to your account appears in this library.'}
                                </p>
                            </div>

                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="space-y-2">
                                <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Certificate library
                                </p>
                                <h3 className="text-xl font-semibold text-white">
                                    {generatedCertificates.length
                                        ? `${generatedCertificates.length} certificate${generatedCertificates.length > 1 ? 's' : ''} available`
                                        : 'No generated certificate yet'}
                                </h3>
                                <p className="text-sm leading-6 text-white/58">
                                    {certificate?.latest_certificate
                                                ? `Latest: ${certificate.latest_certificate.type_label}, version ${certificate.latest_certificate.version}, generated ${certificate.latest_certificate.generated_at}`
                                                : 'No generated certificate is available yet.'}
                                </p>
                            </div>

                                    <div className="rounded-full border border-white/12 bg-black/25 p-3 text-white/70">
                                        {isDownloadReady ? (
                                            <Award className="size-5" />
                                        ) : (
                                            <Lock className="size-5" />
                                        )}
                                    </div>
                            </div>

                            {generatedCertificates.length ? (
                                <div className="space-y-3">
                                    {generatedCertificates.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-[20px] border border-white/10 bg-black/20 px-4 py-4"
                                        >
                                            <div>
                                                <p className="text-sm font-semibold text-white">
                                                    {item.type_label}
                                                </p>
                                                <p className="mt-1 text-xs leading-6 text-white/55">
                                                    Version {item.version}, generated {item.generated_at}
                                                </p>
                                            </div>

                                            <Button
                                                asChild
                                                variant="outline"
                                                className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                            >
                                                <a href={item.download_url}>
                                                    <Download className="mr-2 size-4" />
                                                    Download
                                                </a>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            ) : null}

                            <div className="mt-6 flex flex-wrap gap-3">
                                    {certificate?.cta_kind === 'download' ? (
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <a href={certificate?.cta_url ?? '#'}>
                                                <Download className="mr-2 size-4" />
                                                {certificate?.cta_label ?? 'Download Latest Certificate'}
                                            </a>
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            className="rounded-full bg-[#d5462f] px-5 text-white hover:bg-[#e2553d]"
                                        >
                                            <Link href={certificate?.cta_url ?? route('modules.index')}>
                                                <ChevronRight className="mr-2 size-4" />
                                                {certificate?.cta_label ?? 'Browse Modules'}
                                            </Link>
                                        </Button>
                                    )}

                                    <Button
                                        asChild
                                        variant="outline"
                                        className="rounded-full border-white/15 bg-white/5 text-white hover:bg-white/10 hover:text-white"
                                    >
                                        <Link href={route('modules.index')}>Back to Modules</Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-5 backdrop-blur-sm">
                        <div className="flex h-full flex-col gap-5 rounded-[22px] border border-white/8 bg-black/20 p-5">
                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Current state
                                </p>
                                <p className="mt-3 text-lg font-semibold text-white">
                                    {isDownloadReady ? 'Certificate ready' : 'Awaiting generation'}
                                </p>
                                <p className="mt-3 text-sm leading-6 text-white/58">
                                    {isDownloadReady
                                        ? 'This module now acts as your student certificate library. Review and download every generated certificate available for your account.'
                                        : certificate?.description}
                                </p>
                            </div>

                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Guidance
                                </p>
                                <p className="mt-3 text-sm leading-6 text-white/58">
                                    {isDownloadReady
                                        ? 'This module now acts as your certificate library. Download any generated certificate listed here whenever you need it.'
                                        : 'Your certificate milestone is visible because your accessible tier path is ready. If the files are not here yet, the next step is certificate generation from the admin side.'}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
