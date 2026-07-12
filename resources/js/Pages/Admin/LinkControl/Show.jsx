import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, useForm } from "@inertiajs/react";

export default function LinkControlShow({
    linkControlSetting,
    status,
    warning,
}) {
    const { data, setData, patch, processing, errors } = useForm({
        google_play_url: linkControlSetting.google_play_url ?? "",
        app_store_url: linkControlSetting.app_store_url ?? "",
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route("admin.link-control.update"));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Link Control
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage the QR code and mobile store links used in the student experience.
                    </p>
                </div>
            }
        >
            <Head title="Link Control" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {status === "link-control-updated" ? (
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            Link Control has been updated.
                        </div>
                    ) : null}
                    {status === "link-control-updated-with-warning" ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            Link Control has been updated with a QR fallback.
                        </div>
                    ) : null}
                    {warning ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {warning}
                        </div>
                    ) : null}

                    <div className="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                        <div className="rounded-[5px] bg-white p-6 shadow-sm">
                            <div className="text-sm font-medium text-gray-700">
                                Current QR Preview
                            </div>
                            <div className="mt-4 flex justify-center rounded-[5px] border border-slate-200 bg-slate-50 p-4">
                                {linkControlSetting.qr_image_url ? (
                                    <img
                                        src={linkControlSetting.qr_image_url}
                                        alt="Current QR code"
                                        className="h-60 w-60 rounded-[5px] object-contain"
                                    />
                                ) : (
                                    <div className="flex h-60 w-60 items-center justify-center rounded-[5px] border border-dashed border-slate-300 text-center text-sm text-slate-500">
                                        QR code will appear after you save the store links.
                                    </div>
                                )}
                            </div>
                            <div className="mt-4 space-y-2 text-sm text-slate-600">
                                <p>
                                    The QR code always points to one public
                                    YogaFX download page.
                                </p>
                                <a
                                    href={linkControlSetting.download_page_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex text-sm font-medium text-slate-900 underline underline-offset-4"
                                >
                                    Open public download page
                                </a>
                            </div>
                        </div>

                        <div className="rounded-[5px] bg-white p-6 shadow-sm">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <label
                                        htmlFor="google_play_url"
                                        className="text-sm font-medium text-gray-700"
                                    >
                                        Google Play Link
                                    </label>
                                    <input
                                        id="google_play_url"
                                        type="url"
                                        value={data.google_play_url}
                                        onChange={(event) =>
                                            setData("google_play_url", event.target.value)
                                        }
                                        placeholder="https://play.google.com/store/apps/details?id=..."
                                        className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        When saved, YogaFX regenerates the QR
                                        automatically.
                                    </p>
                                    {errors.google_play_url ? (
                                        <div className="mt-2 text-sm text-rose-600">
                                            {errors.google_play_url}
                                        </div>
                                    ) : null}
                                </div>

                                <div>
                                    <label
                                        htmlFor="app_store_url"
                                        className="text-sm font-medium text-gray-700"
                                    >
                                        App Store Link
                                    </label>
                                    <input
                                        id="app_store_url"
                                        type="url"
                                        value={data.app_store_url}
                                        onChange={(event) =>
                                            setData("app_store_url", event.target.value)
                                        }
                                        placeholder="https://apps.apple.com/..."
                                        className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    {errors.app_store_url ? (
                                        <div className="mt-2 text-sm text-rose-600">
                                            {errors.app_store_url}
                                        </div>
                                    ) : null}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                                >
                                    Save Link Control
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
