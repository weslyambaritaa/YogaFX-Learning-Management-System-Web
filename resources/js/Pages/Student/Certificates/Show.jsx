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
                <section className="relative overflow-hidden rounded-[32px] border border-white/10 bg-[#120f0f] shadow-[0_24px_90px_rgba(0,0,0,0.35)]">
                    <div className="absolute inset-0">
                        {module.thumbnail_url ? (
                            <img
                                src={module.thumbnail_url}
                                alt={module.title}
                                className="h-full w-full object-cover opacity-55"
                            />
                        ) : (
                            <div className="h-full w-full bg-[radial-gradient(circle_at_20%_18%,_rgba(211,101,52,0.45),_transparent_30%),linear-gradient(160deg,_#2f1d16_0%,_#120f0e_100%)]" />
                        )}
                    </div>
                    <div className="absolute inset-0 bg-[linear-gradient(180deg,_rgba(0,0,0,0.22)_0%,_rgba(0,0,0,0.72)_72%,_rgba(0,0,0,0.92)_100%)]" />

                    <div className="relative flex min-h-[420px] flex-col justify-end gap-6 px-6 py-8 sm:px-8 lg:px-10 lg:py-10">
                        <div className="max-w-3xl space-y-4">
                            <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                                Module {module.sort_order}
                            </p>
                            <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                                {certificate?.title ?? module.title}
                            </h1>
                            <p className="max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
                                {certificate?.description ?? module.description}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <div className="rounded-full border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/78 backdrop-blur">
                                {certificate?.eligibility_label ?? 'Certificate access'}
                            </div>
                                <div className="rounded-full border border-white/12 bg-white/5 px-4 py-2 text-sm text-white/78 backdrop-blur">
                                {isDownloadReady ? 'Certificates available' : 'Waiting for certificate file'}
                            </div>
                        </div>
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
                                        : 'Your learning path is ready, certificate file is still pending'}
                                </h2>
                                <p className="text-sm leading-7 text-white/60">
                                    {certificate?.support_note}
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
                                                : 'The certificate module is open because your assignment approvals have reached certificate readiness, but there is no generated certificate file yet.'}
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
                                    {certificate?.description}
                                </p>
                            </div>

                            <div className="rounded-[24px] border border-white/10 bg-white/5 p-5">
                                <p className="text-xs uppercase tracking-[0.2em] text-white/45">
                                    Guidance
                                </p>
                                <p className="mt-3 text-sm leading-6 text-white/58">
                                    {isDownloadReady
                                        ? 'This module now acts as your certificate library. Download any generated certificate listed here whenever you need it.'
                                        : 'Your certificate milestone is visible because your assignment approvals are ready. If the files are not here yet, the next step is certificate generation from the admin side.'}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
