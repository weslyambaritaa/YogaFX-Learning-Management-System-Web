import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

function buildNumericInitial(question) {
    if (
        question.saved.answer_number !== null
        && question.saved.answer_number !== undefined
    ) {
        return question.saved.answer_number;
    }

    if (
        question.starting_score !== null
        && question.starting_score !== undefined
        && question.starting_score !== ''
    ) {
        return question.starting_score;
    }

    return '';
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

export default function AssessmentPreview({ assessment, question, preview }) {
    const [remaining] = useState('Preview');
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

    useEffect(() => {
        setData({
            option_id: question.saved.option_ids?.[0] ?? '',
            option_ids: question.saved.option_ids ?? [],
            answer_text: question.saved.answer_text ?? '',
            answer_number: buildNumericInitial(question),
        });
    }, [question.id]);

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
    const hasOptionSelection = optionSelectionIds(question, data).length > 0;
    const canSubmitOptionQuestion = question.has_correctness_gate
        ? optionFeedback.isGateComplete
        : question.required
          ? hasOptionSelection
          : true;

    const toggleOption = (optionId) => {
        const next = data.option_ids.includes(optionId)
            ? data.option_ids.filter((value) => value !== optionId)
            : [...data.option_ids, optionId];
        setData('option_ids', next);
    };

    const selectSingleOption = (optionId) => {
        setData('option_id', optionId);
        setData('option_ids', [optionId]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('admin.assessments.preview.answer', assessment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <div className="text-sm text-slate-500">
                            Admin Assessment Preview
                        </div>
                        <h2 className="text-2xl font-semibold text-slate-900">
                            {assessment.title}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Simulation only. This preview follows the student flow without creating attempts, answers, or progress records.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                            {remaining}
                        </div>
                        <div className="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">
                            Screen {assessment.progress.current}/
                            {assessment.progress.total}
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`${assessment.title} Preview`} />

            <div
                className="py-10"
                style={{
                    background:
                        assessment.design.section_background
                        || 'linear-gradient(180deg, #f4f2ec 0%, #f7faf7 45%, #f4f7f4 100%)',
                }}
            >
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Preview mode is fully interactive but 100% ephemeral.
                    </div>

                    {assessment.show_progress_bar && (
                        <div className="overflow-hidden rounded-full bg-white/70">
                            <div
                                className="h-2 rounded-full bg-slate-900 transition-all"
                                style={{ width: progressWidth }}
                            />
                        </div>
                    )}

                    <div className="rounded-[36px] border border-white/60 bg-white/90 p-6 shadow-[0_24px_60px_rgba(15,23,42,0.08)] backdrop-blur md:p-8">
                        {assessment.design.logo_url && (
                            <div
                                className={[
                                    'mb-8 flex',
                                    assessment.design.logo_alignment === 'right'
                                        ? 'justify-end'
                                        : assessment.design.logo_alignment === 'center'
                                          ? 'justify-center'
                                          : 'justify-start',
                                ].join(' ')}
                            >
                                {assessment.design.logo_link ? (
                                    <a
                                        href={assessment.design.logo_link}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={assessment.design.logo_url}
                                            alt="Assessment logo"
                                            className="object-contain"
                                            style={{
                                                maxWidth:
                                                    assessment.design.logo_max_width
                                                    || 200,
                                            }}
                                        />
                                    </a>
                                ) : (
                                    <img
                                        src={assessment.design.logo_url}
                                        alt="Assessment logo"
                                        className="object-contain"
                                        style={{
                                            maxWidth:
                                                assessment.design.logo_max_width || 200,
                                        }}
                                    />
                                )}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-8">
                            {question.show_instruction
                                && question.instruction_text && (
                                    <div className="rounded-2xl border border-[#dde4d9] bg-[#f4f8f2] px-4 py-3 text-sm text-slate-600">
                                        {question.instruction_text}
                                    </div>
                                )}

                            <div className="space-y-3">
                                <div className="text-xs font-semibold uppercase tracking-[0.18em] text-[#5f7462]">
                                    Assessment Preview Screen
                                </div>
                                <h3 className="text-3xl font-semibold tracking-tight text-slate-900">
                                    {question.title || 'Untitled question'}
                                </h3>
                                <div
                                    className="text-base leading-8 text-slate-600"
                                    dangerouslySetInnerHTML={{
                                        __html:
                                            question.question_text
                                            || 'No content has been added for this screen yet.',
                                    }}
                                />
                            </div>

                            {isInfoScreen ? (
                                <div className="rounded-3xl border border-slate-200 bg-[#f8f7f2] px-5 py-6 text-sm text-slate-600">
                                    This info screen presents content only and will move forward without storing an answer.
                                </div>
                            ) : isOptionBased ? (
                                <div
                                    className="grid gap-3"
                                    style={{
                                        gridTemplateColumns:
                                            question.question_type
                                            === 'image_button'
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
                                            'rounded-3xl border px-4 py-4 text-left transition',
                                            selectedState === 'correct'
                                                ? 'border-emerald-300 bg-emerald-500/12 text-emerald-950'
                                                : selectedState === 'incorrect'
                                                  ? 'border-rose-300 bg-rose-500/12 text-rose-950'
                                                  : selected
                                                    ? 'border-slate-900 bg-slate-900 text-white'
                                                    : 'border-slate-200 bg-white text-slate-900 hover:border-slate-400',
                                        ].join(' ');

                                        if (
                                            question.question_type === 'image_button'
                                        ) {
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
                                                            className="h-44 w-full rounded-2xl object-cover"
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
                                            <label className="text-sm font-medium text-slate-700">
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
                                            />
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <input
                                                type="range"
                                                min={
                                                    question.score_range_min ?? 0
                                                }
                                                max={
                                                    question.score_range_max ?? 10
                                                }
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
                                                className="w-full accent-slate-900"
                                            />
                                            <div className="flex items-center justify-between text-sm text-slate-500">
                                                <span>
                                                    {question.left_label || 'Low'}
                                                </span>
                                                <span>
                                                    {question.center_label
                                                        || 'Balanced'}
                                                </span>
                                                <span>
                                                    {question.right_label || 'High'}
                                                </span>
                                            </div>
                                            {question.show_score_tooltip && (
                                                <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                                    {question.score_tooltip_format
                                                        || 'Selected value will be used as raw score.'}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        Your Answer
                                    </label>
                                    {question.input_type === 'textarea'
                                    || (question.character_limit || 0) > 140 ? (
                                        <Textarea
                                            value={data.answer_text}
                                            onChange={(event) =>
                                                setData(
                                                    'answer_text',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-32"
                                        />
                                    ) : (
                                        <Input
                                            type={question.input_type || 'text'}
                                            value={data.answer_text}
                                            onChange={(event) =>
                                                setData(
                                                    'answer_text',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                    {question.character_limit && (
                                        <div className="text-xs text-slate-500">
                                            {String(data.answer_text || '').length}/
                                            {question.character_limit}{' '}
                                            characters
                                        </div>
                                    )}
                                </div>
                            )}

                            {isOptionBased && optionFeedback.message && (
                                <div
                                    className={[
                                        'rounded-2xl border px-4 py-3 text-sm font-medium',
                                        optionFeedback.tone === 'success'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                                            : 'border-rose-200 bg-rose-50 text-rose-900',
                                    ].join(' ')}
                                >
                                    {optionFeedback.message}
                                </div>
                            )}

                            {Object.keys(errors).length > 0 && (
                                <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                    {Object.values(errors)[0]}
                                </div>
                            )}

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex flex-wrap gap-3">
                                    {preview.can_go_back && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'admin.assessments.preview.back',
                                                        assessment.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Back
                                        </Button>
                                    )}

                                    <Button type="button" variant="outline" asChild>
                                        <Link
                                            href={route(
                                                'admin.assessments.preview',
                                                assessment.id,
                                            )}
                                            data={{ restart: 1 }}
                                        >
                                            Restart Preview
                                        </Link>
                                    </Button>
                                </div>

                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={
                                        processing
                                        || (isOptionBased
                                            && !canSubmitOptionQuestion)
                                    }
                                >
                                    Continue
                                </Button>
                            </div>
                        </form>

                        {assessment.design.footer_content && (
                            <div className="mt-8 border-t border-slate-200 pt-6 text-sm text-slate-500">
                                {assessment.design.footer_content}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
