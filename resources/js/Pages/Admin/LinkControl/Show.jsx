import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, useForm } from "@inertiajs/react";

export default function LinkControlShow({ linkControlSetting, status }) {
    const { data, setData, patch, processing, errors } = useForm({
        qr_image: null,
        google_play_url: linkControlSetting.google_play_url ?? "",
        app_store_url: linkControlSetting.app_store_url ?? "",
    });

    const submit = (event) => {
        event.preventDefault();
        patch(route("admin.link-control.update"), {
            forceFormData: true,
        });
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
                                        No QR code uploaded yet.
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="rounded-[5px] bg-white p-6 shadow-sm">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <label
                                        htmlFor="qr_image"
                                        className="text-sm font-medium text-gray-700"
                                    >
                                        QR Image
                                    </label>
                                    <input
                                        id="qr_image"
                                        type="file"
                                        accept="image/*"
                                        onChange={(event) =>
                                            setData("qr_image", event.target.files?.[0] ?? null)
                                        }
                                        className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <p className="mt-1 text-xs text-slate-500">
                                        Upload a square QR image. Maximum file size is 10 MB.
                                    </p>
                                    {errors.qr_image ? (
                                        <div className="mt-2 text-sm text-rose-600">
                                            {errors.qr_image}
                                        </div>
                                    ) : null}
                                </div>

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
