import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';

export default function ScoreboardMetaForm({
    data,
    setData,
    errors,
    processing,
    statusOptions,
    scoringModeOptions,
    resultModeOptions,
    onSubmit,
    submitLabel,
    currentThumbnailUrl = null,
}) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">Title</label>
                    <Input
                        value={data.title}
                        onChange={(event) => setData('title', event.target.value)}
                        placeholder="Morning Readiness Scoreboard"
                    />
                    {errors.title && (
                        <p className="text-sm text-rose-600">{errors.title}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">Slug</label>
                    <Input
                        value={data.slug}
                        onChange={(event) => setData('slug', event.target.value)}
                        placeholder="morning-readiness-scoreboard"
                    />
                    {errors.slug && <p className="text-sm text-rose-600">{errors.slug}</p>}
                </div>
            </div>

            <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">Description</label>
                <Textarea
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                    placeholder="Describe the assessment experience and intended outcome."
                    className="min-h-28"
                />
                {errors.description && (
                    <p className="text-sm text-rose-600">{errors.description}</p>
                )}
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">Status</label>
                    <select
                        value={data.status}
                        onChange={(event) => setData('status', event.target.value)}
                        className="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"
                    >
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {errors.status && (
                        <p className="text-sm text-rose-600">{errors.status}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">
                        Duration (minutes)
                    </label>
                    <Input
                        type="number"
                        min="1"
                        value={data.duration_minutes}
                        onChange={(event) =>
                            setData('duration_minutes', event.target.value)
                        }
                        placeholder="15"
                    />
                    {errors.duration_minutes && (
                        <p className="text-sm text-rose-600">
                            {errors.duration_minutes}
                        </p>
                    )}
                </div>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">Scoring Mode</label>
                    <select
                        value={data.scoring_mode}
                        onChange={(event) => setData('scoring_mode', event.target.value)}
                        className="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"
                    >
                        {scoringModeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {errors.scoring_mode && (
                        <p className="text-sm text-rose-600">{errors.scoring_mode}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <label className="text-sm font-medium text-slate-700">Result Mode</label>
                    <select
                        value={data.result_mode}
                        onChange={(event) => setData('result_mode', event.target.value)}
                        className="flex h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm"
                    >
                        {resultModeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    {errors.result_mode && (
                        <p className="text-sm text-rose-600">{errors.result_mode}</p>
                    )}
                </div>
            </div>

            <div className="space-y-2">
                <label className="text-sm font-medium text-slate-700">Thumbnail</label>
                <input
                    type="file"
                    accept="image/*"
                    onChange={(event) =>
                        setData('thumbnail', event.target.files?.[0] ?? null)
                    }
                    className="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                />
                {errors.thumbnail && (
                    <p className="text-sm text-rose-600">{errors.thumbnail}</p>
                )}
                {currentThumbnailUrl && (
                    <img
                        src={currentThumbnailUrl}
                        alt="Current scoreboard thumbnail"
                        className="h-40 w-full rounded-2xl object-cover md:w-72"
                    />
                )}
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <label className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(event) =>
                            setData('is_active', event.target.checked)
                        }
                        className="mt-1 size-4 rounded border-slate-300"
                    />
                    <div>
                        <div className="font-medium text-slate-900">Operationally Active</div>
                        <div className="text-sm text-slate-500">
                            Student access follows this switch.
                        </div>
                    </div>
                </label>

                <label className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <input
                        type="checkbox"
                        checked={data.show_progress_bar}
                        onChange={(event) =>
                            setData('show_progress_bar', event.target.checked)
                        }
                        className="mt-1 size-4 rounded border-slate-300"
                    />
                    <div>
                        <div className="font-medium text-slate-900">Show Progress Bar</div>
                        <div className="text-sm text-slate-500">
                            Display screen progress to students.
                        </div>
                    </div>
                </label>

                <label className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <input
                        type="checkbox"
                        checked={data.allow_back_navigation}
                        onChange={(event) =>
                            setData('allow_back_navigation', event.target.checked)
                        }
                        className="mt-1 size-4 rounded border-slate-300"
                    />
                    <div>
                        <div className="font-medium text-slate-900">Allow Back Navigation</div>
                        <div className="text-sm text-slate-500">
                            Students may revisit earlier screens.
                        </div>
                    </div>
                </label>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
