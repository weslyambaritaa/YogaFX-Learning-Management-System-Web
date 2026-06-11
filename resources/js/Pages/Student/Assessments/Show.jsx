import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function formatRemaining(expiresAt) {
    if (!expiresAt) {
        return 'Untimed';
    }

    const totalSeconds = Math.max(
        0,
        Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000),
    );
    const minutes = Math.floor(totalSeconds / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (totalSeconds % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
}

function buildNumericInitial(question) {
    if (
        question.saved.answer_number !== null
        && question.saved.answer_number !== undefined
    ) {
        return question.saved.answer_number;
    }

    if (
        question.question_type === 'sliding_scale' &&
        question.starting_score !== null
        && question.starting_score !== undefined
        && question.starting_score !== ''
    ) {
        return question.starting_score;
    }

    return '';
}

function shouldUseMultilineInput(question) {
    return question.input_type === 'multi_line' || (question.character_limit || 0) > 140;
}

function getImageFitClass(question) {
    return question.answer_image_fit === 'contain' ? 'object-contain' : 'object-cover';
}

function getScaleBounds(question) {
    const min = Number.isFinite(Number(question.score_range_min))
        ? Math.trunc(Number(question.score_range_min))
        : 1;
    const max = Number.isFinite(Number(question.score_range_max))
        ? Math.trunc(Number(question.score_range_max))
        : Math.max(min, 5);

    return {
        min,
        max: Math.max(max, min),
    };
}

function getIntegerScaleValues(question) {
    const { min, max } = getScaleBounds(question);

    return Array.from({ length: max - min + 1 }, (_, index) => min + index);
}

function groupScaleValues(question) {
    const scaleValues = getIntegerScaleValues(question);
    const sectionCount =
        question.question_type === 'divided_scale'
            ? Math.max(1, Number(question.section_count || 1))
            : 1;
    const valuesPerGroup = Math.ceil(scaleValues.length / sectionCount);
    const groups = [];

    for (let index = 0; index < scaleValues.length; index += valuesPerGroup) {
        groups.push(scaleValues.slice(index, index + valuesPerGroup));
    }

    return groups;
}

function checkboxSelectionLimitMessage(question) {
    if (question.question_type !== 'multiple_choice_checkboxes') {
        return null;
    }

    const min = Number(question.min_count || 0);
    const max = Number(question.max_count || 0);

    if (min > 0 && max > 0) {
        return `Choose between ${min} and ${max} answers.`;
    }

    if (min > 0) {
        return `Choose at least ${min} answer${min === 1 ? '' : 's'}.`;
    }

    if (max > 0) {
        return `Choose no more than ${max} answers.`;
    }

    return null;
}

function optionSelectionIds(question, data) {
    return question.allow_multi_select
        ? (data.option_ids ?? []).map((value) => Number(value))
        : data.option_id === '' || data.option_id === null || data.option_id === undefined
          ? []
          : [Number(data.option_id)];
}

function evaluateOptionFeedback(question, data) {
    if (!question.has_correctness_gate) {
        return {
            isGateComplete: true,
            isCorrect: true,
            message: null,
            tone: null,
            selectedStateMap: {},
        };
    }

    const selectedIds = optionSelectionIds(question, data);
    const selectedIdSet = new Set(selectedIds);
    const correctIds = question.options
        .filter((option) => option.is_correct)
        .map((option) => Number(option.id));
    const correctIdSet = new Set(correctIds);
    const selectedStateMap = {};

    question.options.forEach((option) => {
        const optionId = Number(option.id);

        if (!selectedIdSet.has(optionId)) {
            return;
        }

        selectedStateMap[optionId] = correctIdSet.has(optionId)
            ? 'correct'
            : 'incorrect';
    });

    if (selectedIds.length === 0) {
        return {
            isGateComplete: false,
            isCorrect: false,
            message: null,
            tone: null,
            selectedStateMap,
        };
    }

    const isExactMatch =
        selectedIds.length === correctIds.length
        && selectedIds.every((id) => correctIdSet.has(id));

    return {
        isGateComplete: isExactMatch,
        isCorrect: isExactMatch,
        message: isExactMatch
            ? 'Correct!'
            : 'Oops!!! Wrong Answer! Please refer to your workbook and try again.',
        tone: isExactMatch ? 'success' : 'error',
        selectedStateMap,
    };
}

export default function AssessmentShow({
    lesson,
    assessment,
    attempt,
    question,
    canGoBack,
    isLastQuestion,
}) {
    const [remaining, setRemaining] = useState(
        formatRemaining(assessment.timer.expires_at),
    );
    const isOptionBased = [
        'yes_no_maybe',
        'multiple_choice_checkboxes',
        'multiple_choice_buttons',
        'radio_buttons',
        'image_button',
    ].includes(question.question_type);
    const isNumericBased = [
        'sliding_scale',
        'linear_scale',
        'divided_scale',
        'numeric',
    ].includes(question.question_type);
    const isInfoScreen = question.question_type === 'info_screen';
    const imageColumns = Math.min(
        Math.max(Number(question.answers_per_row || 2), 1),
        4,
    );

    const { data, setData, post, processing, errors } = useForm({
        option_id: question.saved.option_ids?.[0] ?? '',
        option_ids: question.saved.option_ids ?? [],
        answer_text: question.saved.answer_text ?? '',
        answer_number: buildNumericInitial(question),
    });
    const [selectionFeedback, setSelectionFeedback] = useState(null);

    useEffect(() => {
        setData({
            option_id: question.saved.option_ids?.[0] ?? '',
            option_ids: question.saved.option_ids ?? [],
            answer_text: question.saved.answer_text ?? '',
            answer_number: buildNumericInitial(question),
        });
        setSelectionFeedback(null);
    }, [question.id]);

    useEffect(() => {
        if (!assessment.timer.expires_at) {
            return undefined;
        }

        const interval = window.setInterval(() => {
            setRemaining(formatRemaining(assessment.timer.expires_at));
        }, 1000);

        return () => window.clearInterval(interval);
    }, [assessment.timer.expires_at]);

    const progressWidth = useMemo(() => {
        if (!assessment.show_progress_bar || assessment.progress.total === 0) {
            return '0%';
        }

        return `${(assessment.progress.current / assessment.progress.total) * 100}%`;
    }, [
        assessment.progress.current,
        assessment.progress.total,
        assessment.show_progress_bar,
    ]);
    const optionFeedback = useMemo(
        () => evaluateOptionFeedback(question, data),
        [data, question],
    );
    const selectedOptionCount = optionSelectionIds(question, data).length;
    const hasOptionSelection = selectedOptionCount > 0;
    const satisfiesMinSelection = question.min_count
        ? selectedOptionCount >= Number(question.min_count)
        : true;
    const satisfiesMaxSelection = question.max_count
        ? selectedOptionCount <= Number(question.max_count)
        : true;
    const canSubmitOptionQuestion = question.has_correctness_gate
        ? optionFeedback.isGateComplete
        : (question.required
            ? hasOptionSelection
            : true)
            && satisfiesMinSelection
            && satisfiesMaxSelection;

    const toggleOption = (optionId) => {
        const isSelected = data.option_ids.includes(optionId);
        const next = isSelected
            ? data.option_ids.filter((value) => value !== optionId)
            : [...data.option_ids, optionId];
        const maxCount = Number(question.max_count || 0);

        if (!isSelected && maxCount > 0 && next.length > maxCount) {
            setSelectionFeedback(`You can select up to ${maxCount} answers for this question.`);

            return;
        }

        setSelectionFeedback(null);
        setData('option_ids', next);
    };

    const selectSingleOption = (optionId) => {
        setSelectionFeedback(null);
        setData('option_id', optionId);
        setData('option_ids', [optionId]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('assessments.answer', { lesson: lesson.id, attempt: attempt.id }));
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-[#050505]"
            header={
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <div className="text-sm text-white/52">{lesson.title}</div>
                        <h2 className="text-2xl font-semibold text-white">
                            {assessment.title}
                        </h2>
                    </div>
                    <div className="flex flex-wrap items-center gap-3 lg:justify-end">
                        <div className="rounded-full border border-red-400/20 bg-red-500/15 px-4 py-2 text-sm font-semibold text-red-100">
                            {remaining}
                        </div>
                        <div className="rounded-full border border-white/10 bg-white/6 px-4 py-2 text-sm font-medium text-white/72">
                            {assessment.progress.current} out of{' '}
                            {assessment.progress.total}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={assessment.title} />

            <div
                className="py-10"
                style={{
                    background:
                        assessment.design.section_background
                        || 'radial-gradient(circle at top, rgba(226,72,72,0.18), transparent 24%), linear-gradient(180deg, #111111 0%, #080808 40%, #030303 100%)',
                }}
            >
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {assessment.show_progress_bar && (
                        <div className="overflow-hidden rounded-full bg-white/10">
                            <div
                                className="h-2 rounded-full transition-all"
                                style={{
                                    width: progressWidth,
                                    background:
                                        'linear-gradient(90deg, #e24848 0%, #ff6f6f 100%)',
                                }}
                            />
                        </div>
                    )}

                    {assessment.design.logo_url && (
                        <div className="flex justify-center">
                            <div className="rounded-full border border-white/10 bg-white/5 px-6 py-4 shadow-[0_18px_60px_rgba(0,0,0,0.25)]">
                                {assessment.design.logo_link ? (
                                    <a
                                        href={assessment.design.logo_link}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={assessment.design.logo_url}
                                            alt="Assessment logo"
                                            className="mx-auto object-contain"
                                            style={{
                                                maxWidth:
                                                    assessment.design.logo_max_width
                                                    || 180,
                                            }}
                                        />
                                    </a>
                                ) : (
                                    <img
                                        src={assessment.design.logo_url}
                                        alt="Assessment logo"
                                        className="mx-auto object-contain"
                                        style={{
                                            maxWidth:
                                                assessment.design.logo_max_width || 180,
                                        }}
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    <div className="rounded-[40px] border border-white/10 bg-white/[0.045] p-6 shadow-[0_30px_100px_rgba(0,0,0,0.4)] backdrop-blur md:p-10">
                        <div className="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div className="space-y-3">
                                <div className="text-xs font-semibold uppercase tracking-[0.24em] text-red-200/90">
                                    Assessment
                                </div>
                                <h3 className="max-w-3xl text-3xl font-semibold tracking-tight text-white md:text-4xl">
                                    {question.title || 'Untitled question'}
                                </h3>
                                <div className="text-sm uppercase tracking-[0.18em] text-white/40">
                                    {assessment.progress.current} out of{' '}
                                    {assessment.progress.total}
                                </div>
                            </div>

                            <div className="rounded-2xl border border-white/10 bg-black/20 px-5 py-3 text-sm text-white/70">
                                {isLastQuestion
                                    ? 'Final step'
                                    : 'Continue when your answer is ready'}
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-8">
                            {question.show_instruction && question.instruction_text && (
                                <div className="rounded-2xl border border-white/10 bg-white/6 px-4 py-3 text-sm text-white/72">
                                    {question.instruction_text}
                                </div>
                            )}

                            <div
                                className="max-w-4xl text-lg leading-8 text-white/82"
                                dangerouslySetInnerHTML={{
                                    __html:
                                        question.question_text
                                        || 'No content has been added for this screen yet.',
                                }}
                            />

                            {isInfoScreen ? (
                                <div className="rounded-3xl border border-white/10 bg-white/5 px-5 py-6 text-sm text-white/68">
                                    This info screen presents content only and will move forward without storing an answer.
                                </div>
                            ) : isOptionBased ? (
                                <div
                                    className="grid gap-3"
                                    style={{
                                        gridTemplateColumns:
                                            question.question_type === 'image_button'
                                                ? `repeat(${imageColumns}, minmax(0, 1fr))`
                                                : 'repeat(1, minmax(0, 1fr))',
                                    }}
                                >
                                    {question.options.map((option) => {
                                        const selected = question.allow_multi_select
                                            ? data.option_ids.includes(option.id)
                                            : String(data.option_id)
                                                === String(option.id);
                                        const selectedState =
                                            optionFeedback.selectedStateMap[
                                                Number(option.id)
                                            ];

                                        const sharedClass = [
                                            'rounded-3xl border px-4 py-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-400/60',
                                            selectedState === 'correct'
                                                ? 'border-emerald-300/50 bg-emerald-500/18 text-white shadow-[0_12px_32px_rgba(16,185,129,0.18)]'
                                                : selectedState === 'incorrect'
                                                  ? 'border-rose-300/50 bg-rose-500/18 text-white shadow-[0_12px_32px_rgba(244,63,94,0.18)]'
                                                  : selected
                                                    ? 'border-red-300/50 bg-red-500/18 text-white shadow-[0_12px_32px_rgba(226,72,72,0.18)]'
                                                    : 'border-white/10 bg-white/5 text-white/84 hover:border-white/24 hover:bg-white/8',
                                        ].join(' ');

                                        if (question.question_type === 'image_button') {
                                            return (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    onClick={() =>
                                                        question.allow_multi_select
                                                            ? toggleOption(
                                                                  option.id,
                                                              )
                                                            : selectSingleOption(
                                                                  option.id,
                                                              )
                                                    }
                                                    className={`${sharedClass} overflow-hidden`}
                                                >
                                                    {option.image_url && (
                                                        <img
                                                            src={option.image_url}
                                                            alt={option.label}
                                                            className={`h-44 w-full rounded-2xl ${getImageFitClass(question)}`}
                                                        />
                                                    )}
                                                    {question.show_labels && (
                                                        <div className="mt-3 font-medium">
                                                            {option.label}
                                                        </div>
                                                    )}
                                                </button>
                                            );
                                        }

                                        return (
                                            <button
                                                key={option.id}
                                                type="button"
                                                onClick={() =>
                                                    question.allow_multi_select
                                                        ? toggleOption(option.id)
                                                        : selectSingleOption(
                                                              option.id,
                                                          )
                                                }
                                                className={sharedClass}
                                            >
                                                {option.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : isNumericBased ? (
                                <div className="space-y-6">
                                    {question.question_type === 'numeric' ? (
                                        <div className="max-w-sm space-y-2">
                                            <label className="text-sm font-medium text-white/80">
                                                Numeric Answer
                                            </label>
                                            <Input
                                                type="number"
                                                step={
                                                    question.allow_decimals
                                                        ? '0.01'
                                                        : '1'
                                                }
                                                value={data.answer_number}
                                                onChange={(event) =>
                                                    setData(
                                                        'answer_number',
                                                        event.target.value,
                                                    )
                                                }
                                                className="border-white/10 bg-white/5 text-white placeholder:text-white/30"
                                            />
                                            <div className="text-xs text-white/48">
                                                Allowed range: {question.score_range_min ?? 0} to {question.score_range_max ?? 0}
                                            </div>
                                        </div>
                                    ) : question.question_type === 'sliding_scale' ? (
                                        <div className="space-y-4">
                                            <input
                                                type="range"
                                                min={question.score_range_min ?? 0}
                                                max={question.score_range_max ?? 10}
                                                step={
                                                    question.allow_decimals
                                                        ? '0.01'
                                                        : '1'
                                                }
                                                value={data.answer_number}
                                                onChange={(event) =>
                                                    setData(
                                                        'answer_number',
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full accent-red-500"
                                            />
                                            <div className="flex items-center justify-between text-sm text-white/48">
                                                <span>{question.left_label || 'Low'}</span>
                                                <span>
                                                    {question.center_label
                                                        || 'Balanced'}
                                                </span>
                                                <span>
                                                    {question.right_label || 'High'}
                                                </span>
                                            </div>
                                            {question.show_score_tooltip && (
                                                <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/68">
                                                    {question.score_tooltip_format
                                                        || 'Selected value will be used as raw score.'}
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {groupScaleValues(question).map((group, groupIndex) => (
                                                <div
                                                    key={`scale-group-${groupIndex}`}
                                                    className={[
                                                        'rounded-2xl border border-white/10 bg-white/5 p-4',
                                                        question.question_type === 'linear_scale'
                                                            ? 'overflow-x-auto'
                                                            : '',
                                                    ].join(' ')}
                                                >
                                                    {question.question_type === 'divided_scale' && (
                                                        <div className="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-white/45">
                                                            Section {groupIndex + 1}
                                                        </div>
                                                    )}
                                                    <div
                                                        className={
                                                            question.question_type === 'linear_scale'
                                                                ? 'flex items-center gap-2 whitespace-nowrap'
                                                                : 'grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6'
                                                        }
                                                    >
                                                        {group.map((scaleValue) => {
                                                            const isSelected =
                                                                String(data.answer_number)
                                                                === String(scaleValue);

                                                            return (
                                                                <button
                                                                    key={scaleValue}
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setData(
                                                                            'answer_number',
                                                                            String(scaleValue),
                                                                        )
                                                                    }
                                                                    className={[
                                                                        question.question_type === 'linear_scale'
                                                                            ? 'flex size-9 items-center justify-center rounded-full border text-xs font-semibold transition'
                                                                            : 'rounded-2xl border px-4 py-4 text-center text-sm font-medium transition',
                                                                        isSelected
                                                                            ? 'border-red-300/50 bg-red-500/18 text-white shadow-[0_12px_32px_rgba(226,72,72,0.18)]'
                                                                            : 'border-white/10 bg-white/5 text-white/84 hover:border-white/24 hover:bg-white/8',
                                                                    ].join(' ')}
                                                                >
                                                                    {scaleValue}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                </div>
                                            ))}
                                            <div className="flex items-center justify-between text-sm text-white/48">
                                                <span>{question.left_label || 'Low'}</span>
                                                <span>
                                                    {question.center_label || 'Balanced'}
                                                </span>
                                                <span>{question.right_label || 'High'}</span>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-white/80">
                                        Your Answer
                                    </label>
                                    {shouldUseMultilineInput(question) ? (
                                        <Textarea
                                            value={data.answer_text}
                                            onChange={(event) =>
                                                setData(
                                                    'answer_text',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-32 border-white/10 bg-white/5 text-white placeholder:text-white/30"
                                        />
                                    ) : (
                                        <Input
                                            type="text"
                                            value={data.answer_text}
                                            onChange={(event) =>
                                                setData(
                                                    'answer_text',
                                                    event.target.value,
                                                )
                                            }
                                            className="border-white/10 bg-white/5 text-white placeholder:text-white/30"
                                        />
                                    )}
                                    {question.character_limit && (
                                        <div className="text-xs text-white/42">
                                            {String(data.answer_text || '').length}/
                                            {question.character_limit} characters
                                        </div>
                                    )}
                                </div>
                            )}

                            {isOptionBased && optionFeedback.message && (
                                <div
                                    className={[
                                        'rounded-2xl border px-4 py-3 text-sm font-medium',
                                        optionFeedback.tone === 'success'
                                            ? 'border-emerald-400/30 bg-emerald-500/10 text-emerald-100'
                                            : 'border-rose-400/30 bg-rose-500/10 text-rose-100',
                                    ].join(' ')}
                                >
                                    {optionFeedback.message}
                                </div>
                            )}

                            {checkboxSelectionLimitMessage(question) && (
                                <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/68">
                                    {checkboxSelectionLimitMessage(question)}
                                </div>
                            )}

                            {selectionFeedback && (
                                <div className="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                                    {selectionFeedback}
                                </div>
                            )}

                            {Object.keys(errors).length > 0 && (
                                <div className="rounded-2xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
                                    {Object.values(errors)[0]}
                                </div>
                            )}

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex flex-wrap gap-3">
                                    {canGoBack && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="border-red-400/25 bg-red-500/10 text-red-100 hover:bg-red-500/18 hover:text-white"
                                            onClick={() =>
                                                router.post(
                                                    route('assessments.back', {
                                                        lesson: lesson.id,
                                                        attempt: attempt.id,
                                                    }),
                                                )
                                            }
                                        >
                                            Previous
                                        </Button>
                                    )}
                                </div>

                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={
                                        processing
                                        || (isOptionBased
                                            && !canSubmitOptionQuestion)
                                    }
                                    className="bg-[#e24848] text-white hover:bg-[#f05a5a]"
                                >
                                    {isLastQuestion ? 'Submit Assessment' : 'Next'}
                                </Button>
                            </div>
                        </form>

                        {assessment.design.footer_content && (
                            <div className="mt-8 border-t border-white/10 pt-6 text-sm text-white/42">
                                {assessment.design.footer_content}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
