import { Head, Link } from '@inertiajs/react';

export default function PublicFlowLayout({
    title,
    eyebrow,
    heading,
    description,
    aside,
    children,
}) {
    return (
        <>
            <Head title={title} />

            <div className="min-h-screen bg-[#120f0d] text-white">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(214,84,47,0.18),_transparent_30%),radial-gradient(circle_at_top_right,_rgba(245,158,11,0.12),_transparent_24%),linear-gradient(180deg,_rgba(255,255,255,0.02)_0%,_rgba(0,0,0,0.78)_100%)]" />

                <div className="relative mx-auto flex min-h-screen max-w-[1280px] flex-col px-4 py-6 sm:px-6 lg:px-10">
                    <header className="flex items-center justify-between gap-4">
                        <Link
                            href="/"
                            className="text-sm uppercase tracking-[0.28em] text-white/68 transition hover:text-white"
                        >
                            YogaFX LMS
                        </Link>

                        <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-xs uppercase tracking-[0.22em] text-white/48">
                            Simulated Payment Flow
                        </div>
                    </header>

                    <main className="flex flex-1 items-center py-8 lg:py-12">
                        <div className="grid w-full gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                            <section className="rounded-[32px] border border-white/10 bg-[#15110f] p-6 shadow-[0_30px_100px_rgba(0,0,0,0.4)] sm:p-8 lg:p-10">
                                <div className="space-y-5">
                                    <div className="space-y-3">
                                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                                            {eyebrow}
                                        </p>
                                        <h1 className="max-w-3xl text-4xl font-semibold tracking-[-0.04em] text-white sm:text-5xl">
                                            {heading}
                                        </h1>
                                        <p className="max-w-2xl text-sm leading-7 text-white/64 sm:text-base">
                                            {description}
                                        </p>
                                    </div>

                                    <div className="rounded-[28px] border border-white/10 bg-white/[0.04] p-4 backdrop-blur-sm sm:p-5">
                                        {children}
                                    </div>
                                </div>
                            </section>

                            <aside className="rounded-[32px] border border-white/10 bg-black/25 p-6 backdrop-blur-md sm:p-7">
                                {aside}
                            </aside>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
