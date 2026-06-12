import { Badge } from '@/Components/ui/badge';

function StatusBadge({ status }) {
    if (!status) {
        return <Badge variant="outline">N/A</Badge>;
    }

    return (
        <Badge variant={status === 'correct' ? 'secondary' : 'destructive'}>
            {status === 'correct' ? 'Correct' : 'Incorrect'}
        </Badge>
    );
}

export default function QuestionReviewList({ questions }) {
    return (
        <div className="space-y-4">
            {questions.map((question, index) => (
                <section
                    key={question.id}
                    className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div className="space-y-2">
                            <div className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                Step {index + 1}
                            </div>
                            <h3 className="text-lg font-semibold text-slate-900">
                                {question.title || `Question ${index + 1}`}
                            </h3>
                        </div>

                        <div className="flex items-center gap-2">
                            {question.is_info_screen ? (
                                <Badge variant="outline">Info Screen</Badge>
                            ) : (
                                <StatusBadge status={question.status} />
                            )}
                        </div>
                    </div>

                    {question.question_text && (
                        <div
                            className="mt-4 text-sm leading-7 text-slate-600"
                            dangerouslySetInnerHTML={{ __html: question.question_text }}
                        />
                    )}

                    {question.is_info_screen ? (
                        <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            This info screen was part of the path that was actually traversed.
                        </div>
                    ) : (
                        <div className="mt-5 grid gap-4 lg:grid-cols-[1.35fr_0.95fr]">
                            {question.options.length > 0 ? (
                                <div className="space-y-3">
                                    <div className="text-sm font-medium text-slate-800">
                                        Response
                                    </div>
                                    <div className="space-y-2">
                                        {question.options.map((option) => (
                                            <div
                                                key={option.id}
                                                className={[
                                                    'rounded-2xl border px-4 py-3 text-sm',
                                                    option.is_selected && option.is_correct
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                                                        : option.is_selected
                                                          ? 'border-slate-900 bg-slate-900 text-white'
                                                          : option.is_correct
                                                            ? 'border-emerald-200 bg-white text-slate-900'
                                                            : 'border-slate-200 bg-slate-50 text-slate-700',
                                                ].join(' ')}
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <span>{option.label}</span>
                                                    <div className="flex shrink-0 gap-2">
                                                        {option.is_selected && (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-current text-current"
                                                            >
                                                                User
                                                            </Badge>
                                                        )}
                                                        {option.is_correct && (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-current text-current"
                                                            >
                                                                Correct
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="text-sm font-medium text-slate-800">
                                        Response
                                    </div>
                                    <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                        {question.display_answer || 'No answer provided.'}
                                    </div>
                                </div>
                            )}

                            <div className="space-y-3">
                                <div className="text-sm font-medium text-slate-800">
                                    Review Summary
                                </div>
                                <div className="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-700">
                                    <div>
                                        <span className="font-medium text-slate-900">
                                            Type:
                                        </span>{' '}
                                        {question.question_type}
                                    </div>
                                    {question.selected_labels.length > 0 && (
                                        <div>
                                            <span className="font-medium text-slate-900">
                                                User selection:
                                            </span>{' '}
                                            {question.selected_labels.join(', ')}
                                        </div>
                                    )}
                                    {question.correct_labels.length > 0 && (
                                        <div>
                                            <span className="font-medium text-slate-900">
                                                Correct answer:
                                            </span>{' '}
                                            {question.correct_labels.join(', ')}
                                        </div>
                                    )}
                                    {question.options.length === 0 && question.display_answer && (
                                        <div>
                                            <span className="font-medium text-slate-900">
                                                Submitted value:
                                            </span>{' '}
                                            {question.display_answer}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </section>
            ))}
        </div>
    );
}
