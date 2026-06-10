import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, LibraryBig } from 'lucide-react';

export default function StudentEbooksIndex({ ebooks }) {
    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="pb-16"
        >
            <Head title="Ebooks" />

            <div className="mx-auto flex max-w-[1400px] flex-col gap-8 px-4 pt-8 sm:px-6 lg:px-10">
                <section className="relative overflow-hidden rounded-[32px] border border-white/10 bg-[#120f0f] px-6 py-8 shadow-[0_24px_90px_rgba(0,0,0,0.35)] sm:px-8 lg:px-10">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,_rgba(196,91,49,0.28),_transparent_28%),linear-gradient(180deg,_rgba(255,255,255,0.05)_0%,_rgba(255,255,255,0)_36%),linear-gradient(180deg,_rgba(12,10,10,0.22)_0%,_rgba(12,10,10,0.82)_100%)]" />
                    <div className="relative max-w-3xl space-y-4">
                        <p className="text-xs uppercase tracking-[0.28em] text-[#f2d9c8]">
                            Digital Library
                        </p>
                        <h1 className="text-4xl font-semibold tracking-[-0.03em] text-white sm:text-5xl">
                            Browse your YogaFX library like a premium shelf of study resources.
                        </h1>
                        <p className="max-w-2xl text-sm leading-7 text-white/68 sm:text-base">
                            Ebooks should feel curated, quiet, and content-first, with enough
                            visual presence to invite reading without looking like asset
                            management.
                        </p>
                    </div>
                </section>

                <section className="space-y-5">
                    <div>
                        <p className="text-xs uppercase tracking-[0.24em] text-white/40">
                            Your Shelf
                        </p>
                        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-white">
                            Available ebooks
                        </h2>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {ebooks.map((ebook, index) => (
                            <article
                                key={ebook.id}
                                className="group flex flex-col overflow-hidden rounded-[28px] border border-white/10 bg-white/[0.04] transition duration-300 hover:-translate-y-1 hover:border-white/20 hover:bg-white/[0.06]"
                            >
                                <div className="relative overflow-hidden p-5 pb-0">
                                    <div
                                        className={[
                                            'relative aspect-[3/4] rounded-[24px] border border-white/10 p-5 shadow-[0_18px_45px_rgba(0,0,0,0.3)]',
                                            index % 4 === 0
                                                ? 'bg-[radial-gradient(circle_at_24%_20%,_rgba(223,103,57,0.5),_transparent_28%),linear-gradient(160deg,_#2b1d16_0%,_#120f0e_100%)]'
                                                : index % 4 === 1
                                                  ? 'bg-[radial-gradient(circle_at_74%_20%,_rgba(255,255,255,0.12),_transparent_24%),linear-gradient(160deg,_#211916_0%,_#110f0e_100%)]'
                                                  : index % 4 === 2
                                                    ? 'bg-[radial-gradient(circle_at_30%_18%,_rgba(173,76,38,0.42),_transparent_28%),linear-gradient(160deg,_#261915_0%,_#100d0c_100%)]'
                                                    : 'bg-[radial-gradient(circle_at_64%_20%,_rgba(199,93,45,0.4),_transparent_24%),linear-gradient(160deg,_#241813_0%,_#100d0c_100%)]',
                                        ].join(' ')}
                                    >
                                        <div className="flex h-full flex-col justify-between">
                                            <div className="inline-flex w-fit items-center gap-2 rounded-full border border-white/12 bg-black/25 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-white/70 backdrop-blur">
                                                <LibraryBig className="size-3.5" />
                                                Ebook {ebook.sort_order ?? index + 1}
                                            </div>
                                            <div className="space-y-3">
                                                <h3 className="text-2xl font-semibold tracking-[-0.03em] text-white">
                                                    {ebook.title}
                                                </h3>
                                                <p className="text-sm text-white/55">
                                                    {ebook.file_name}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-1 flex-col justify-between gap-4 p-5">
                                    <div className="inline-flex items-center gap-2 text-sm font-medium text-white/72">
                                        <BookOpen className="size-4 text-[#f15b3a]" />
                                        Ready for preview
                                    </div>

                                    <Button
                                        asChild
                                        className="rounded-full bg-[#f15b3a] text-white hover:bg-[#ff6a49]"
                                    >
                                        <Link href={ebook.preview_url}>Preview Ebook</Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>

                    {ebooks.length === 0 && (
                        <div className="rounded-[28px] border border-white/10 bg-white/[0.04] px-6 py-10 text-center text-white/62">
                            No ebooks are available for your current tier yet.
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
