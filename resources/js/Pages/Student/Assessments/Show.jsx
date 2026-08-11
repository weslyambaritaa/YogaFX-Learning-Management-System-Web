import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
import StudentBackButton from "@/Components/student/StudentBackButton";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, useForm } from "@inertiajs/react";
import { useEffect, useMemo, useState } from "react";
import { Check } from "lucide-react";

function formatRemaining(expiresAt) {
    if (!expiresAt) {
        return "Untimed";
    }

    const totalSeconds = Math.max(
        0,
        Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000),
    );
    const minutes = Math.floor(totalSeconds / 60)
        .toString()
        .padStart(2, "0");
    const seconds = (totalSeconds % 60).toString().padStart(2, "0");

    return `${minutes}:${seconds}`;
}

function buildNumericInitial(question) {
    if (
        question.saved.answer_number !== null &&
        question.saved.answer_number !== undefined
    ) {
        return question.saved.answer_number;
    }

    if (
        question.question_type === "sliding_scale" &&
        question.starting_score !== null &&
        question.starting_score !== undefined &&
        question.starting_score !== ""
    ) {
        return question.starting_score;
    }

    return "";
}

function shouldUseMultilineInput(question) {
    return (
        question.input_type === "multi_line" ||
        (question.character_limit || 0) > 140
    );
}

function getImageFitClass(question) {
    return question.answer_image_fit === "contain"
        ? "object-contain"
        : "object-cover";
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
        question.question_type === "divided_scale"
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
    if (question.question_type !== "multiple_choice_checkboxes") {
        return null;
    }

    const min = Number(question.min_count || 0);
    const max = Number(question.max_count || 0);

    if (min > 0 && max > 0) {
        return `Choose between ${min} and ${max} answers.`;
    }

    if (min > 0) {
        return `Choose at least ${min} answer${min === 1 ? "" : "s"}.`;
    }

    if (max > 0) {
        return `Choose no more than ${max} answers.`;
    }

    return null;
}

function optionSelectionIds(question, data) {
    return question.allow_multi_select
        ? (data.option_ids ?? []).map((value) => Number(value))
        : data.option_id === "" ||
            data.option_id === null ||
            data.option_id === undefined
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
            ? "correct"
            : "incorrect";
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
        selectedIds.length === correctIds.length &&
        selectedIds.every((id) => correctIdSet.has(id));

    return {
        isGateComplete: isExactMatch,
        isCorrect: isExactMatch,
        message: isExactMatch
            ? "Correct!"
            : "Oops!!! Wrong Answer! Please refer to your workbook and try again.",
        tone: isExactMatch ? "success" : "error",
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
        "yes_no_maybe",
        "multiple_choice_checkboxes",
        "multiple_choice_buttons",
        "radio_buttons",
        "image_button",
    ].includes(question.question_type);
    const isNumericBased = [
        "sliding_scale",
        "linear_scale",
        "divided_scale",
        "numeric",
    ].includes(question.question_type);
    const isInfoScreen = question.question_type === "info_screen";
    const imageColumns = Math.min(
        Math.max(Number(question.answers_per_row || 2), 1),
        4,
    );

    const { data, setData, post, processing, errors } = useForm({
        option_id: question.saved.option_ids?.[0] ?? "",
        option_ids: question.saved.option_ids ?? [],
        answer_text: question.saved.answer_text ?? "",
        answer_number: buildNumericInitial(question),
    });
    const [selectionFeedback, setSelectionFeedback] = useState(null);

    useEffect(() => {
        setData({
            option_id: question.saved.option_ids?.[0] ?? "",
            option_ids: question.saved.option_ids ?? [],
            answer_text: question.saved.answer_text ?? "",
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
            return "0%";
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
        : (question.required ? hasOptionSelection : true) &&
          satisfiesMinSelection &&
          satisfiesMaxSelection;

    const toggleOption = (optionId) => {
        const isSelected = data.option_ids.includes(optionId);
        const next = isSelected
            ? data.option_ids.filter((value) => value !== optionId)
            : [...data.option_ids, optionId];
        const maxCount = Number(question.max_count || 0);

        if (!isSelected && maxCount > 0 && next.length > maxCount) {
            setSelectionFeedback(
                `You can select up to ${maxCount} answers for this question.`,
            );

            return;
        }

        setSelectionFeedback(null);
        setData("option_ids", next);
    };

    const selectSingleOption = (optionId) => {
        setSelectionFeedback(null);
        setData("option_id", optionId);
        setData("option_ids", [optionId]);
    };

    const submit = (event) => {
        event.preventDefault();
        post(
            route("assessments.answer", {
                lesson: lesson.id,
                attempt: attempt.id,
            }),
        );
    };

    return (
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-black"
            header={
                <div className="flex flex-col gap-4">
                    <StudentBackButton
                        fallbackHref={route("lessons.show", lesson.id)}
                    />

                    <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div className="min-w-0">
                            <h2 className="text-2xl font-bold text-white">
                                {assessment.title}
                            </h2>
                        </div>

                        <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                            <div className="rounded-[5px] border border-red-400/20 bg-red-500/15 px-4 py-1.5 text-sm font-bold text-red-100">
                                {remaining}
                            </div>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={assessment.title} />

            <div className="relative bg-black pt-4 pb-0 sm:pt-6">
                <div className="mx-auto w-full max-w-4xl px-4 sm:px-6 lg:px-8">
                    {assessment.design.logo_url && (
                        <div className="mb-4 flex justify-center">
                            <div className="bg-transparent">
                                {assessment.design.logo_link ? (
                                    <a
                                        href={assessment.design.logo_link}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <img
                                            src={assessment.design.logo_url}
                                            alt="Assessment logo"
                                            className="mx-auto h-12 object-contain"
                                        />
                                    </a>
                                ) : (
                                    <img
                                        src={assessment.design.logo_url}
                                        alt="Assessment logo"
                                        className="mx-auto h-12 object-contain"
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    <div className="w-full pb-2">
                        <div className="mb-4 flex flex-col items-center text-center">
                            <div className="text-[13px] font-bold tracking-wide text-white">
                                Question {assessment.progress.current} out of{" "}
                                {assessment.progress.total}
                            </div>
                        </div>

                        <form onSubmit={submit} className="space-y-6">
                            {question.show_instruction &&
                                question.instruction_text && (
                                    <div className="mx-auto max-w-2xl rounded-[5px] border border-white/10 bg-white/5 px-4 py-3 text-center text-xs font-bold text-white/80">
                                        {question.instruction_text}
                                    </div>
                                )}

                            <div
                                className="mx-auto max-w-3xl text-center text-2xl font-bold leading-tight text-white md:text-3xl"
                                dangerouslySetInnerHTML={{
                                    __html:
                                        question.question_text ||
                                        "No content has been added for this screen yet.",
                                }}
                            />

                            {isInfoScreen ? (
                                <div className="mx-auto max-w-2xl rounded-[5px] border border-white/10 bg-white/5 px-4 py-5 text-center text-sm font-bold text-white/70">
                                    This screen is informational only.
                                </div>
                            ) : isOptionBased ? (
                                <div
                                    className="mx-auto mt-6 grid max-w-xl gap-3"
                                    style={{
                                        gridTemplateColumns:
                                            question.question_type ===
                                            "image_button"
                                                ? `repeat(${imageColumns}, minmax(0, 1fr))`
                                                : "repeat(1, minmax(0, 1fr))",
                                    }}
                                >
                                    {question.options.map((option) => {
                                        const selected =
                                            question.allow_multi_select
                                                ? data.option_ids.includes(
                                                      option.id,
                                                  )
                                                : String(data.option_id) ===
                                                  String(option.id);
                                        const selectedState =
                                            optionFeedback.selectedStateMap[
                                                Number(option.id)
                                            ];

                                        const sharedClass = [
                                            "group flex cursor-pointer items-center gap-4 rounded-[5px] bg-transparent px-4 py-2.5 text-left outline-none transition hover:bg-white/5 focus-visible:ring-2 focus-visible:ring-red-500/50",
                                            selectedState === "correct"
                                                ? "bg-emerald-500/10"
                                                : selectedState === "incorrect"
                                                  ? "bg-rose-500/10"
                                                  : selected
                                                    ? "bg-white/5"
                                                    : "",
                                        ].join(" ");

                                        if (
                                            question.question_type ===
                                            "image_button"
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
                                                    className={`${sharedClass} flex-col !items-start`}
                                                >
                                                    {option.image_url && (
                                                        <img
                                                            src={
                                                                option.image_url
                                                            }
                                                            alt={option.label}
                                                            className={`h-32 w-full rounded-[5px] border-2 shadow-md transition ${selected ? "border-[#DB202C]" : "border-transparent"} ${getImageFitClass(question)}`}
                                                        />
                                                    )}

                                                    {question.show_labels && (
                                                        <div className="mt-2 flex w-full items-center gap-3 text-lg font-bold text-white">
                                                            <span
                                                                className={[
                                                                    "flex size-5 shrink-0 items-center justify-center border-2 transition-colors",
                                                                    question.allow_multi_select
                                                                        ? "rounded-[4px]"
                                                                        : "rounded-full",
                                                                    selected
                                                                        ? "border-[#DB202C] bg-[#DB202C] text-white"
                                                                        : "border-[#DB202C] bg-transparent text-transparent group-hover:bg-[#DB202C]/20",
                                                                ].join(" ")}
                                                            >
                                                                {question.allow_multi_select ? (
                                                                    <Check className="size-3" />
                                                                ) : (
                                                                    selected && (
                                                                        <span className="size-2 rounded-full bg-white" />
                                                                    )
                                                                )}
                                                            </span>

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
                                                        ? toggleOption(
                                                              option.id,
                                                          )
                                                        : selectSingleOption(
                                                              option.id,
                                                          )
                                                }
                                                className={sharedClass}
                                            >
                                                <span
                                                    className={[
                                                        "flex size-5 shrink-0 items-center justify-center border-2 transition-colors",
                                                        question.allow_multi_select
                                                            ? "rounded-[4px]"
                                                            : "rounded-full",
                                                        selected
                                                            ? "border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_8px_rgba(219,32,44,0.4)]"
                                                            : "border-[#DB202C] bg-transparent text-transparent group-hover:bg-[#DB202C]/20",
                                                    ].join(" ")}
                                                >
                                                    {question.allow_multi_select ? (
                                                        <Check className="size-3" />
                                                    ) : (
                                                        selected && (
                                                            <span className="size-2 rounded-full bg-white" />
                                                        )
                                                    )}
                                                </span>

                                                <span className="text-lg font-bold text-white">
                                                    {option.label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : isNumericBased ? (
                                <div className="mx-auto mt-6 max-w-2xl space-y-4">
                                    {question.question_type === "numeric" ? (
                                        <div className="mx-auto max-w-sm space-y-2">
                                            <label className="block text-center text-sm font-bold text-white/80">
                                                Enter your numeric answer
                                            </label>

                                            <Input
                                                type="number"
                                                step={
                                                    question.allow_decimals
                                                        ? "0.01"
                                                        : "1"
                                                }
                                                value={data.answer_number}
                                                onChange={(event) =>
                                                    setData(
                                                        "answer_number",
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-12 rounded-[5px] border-white/20 bg-white/5 text-center text-xl font-bold text-white placeholder:text-white/30 focus-visible:ring-[#DB202C]"
                                            />

                                            <div className="text-center text-xs font-bold text-white/50">
                                                Allowed range:{" "}
                                                {question.score_range_min ?? 0}{" "}
                                                to{" "}
                                                {question.score_range_max ?? 0}
                                            </div>
                                        </div>
                                    ) : question.question_type ===
                                      "sliding_scale" ? (
                                        <div className="mx-auto max-w-lg space-y-4">
                                            <input
                                                type="range"
                                                min={
                                                    question.score_range_min ??
                                                    0
                                                }
                                                max={
                                                    question.score_range_max ??
                                                    10
                                                }
                                                step={
                                                    question.allow_decimals
                                                        ? "0.01"
                                                        : "1"
                                                }
                                                value={data.answer_number}
                                                onChange={(event) =>
                                                    setData(
                                                        "answer_number",
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-full accent-[#DB202C]"
                                            />

                                            <div className="flex items-center justify-between text-sm font-bold text-white/70">
                                                <span>
                                                    {question.left_label ||
                                                        "Low"}
                                                </span>
                                                <span>
                                                    {question.center_label ||
                                                        "Balanced"}
                                                </span>
                                                <span>
                                                    {question.right_label ||
                                                        "High"}
                                                </span>
                                            </div>

                                            {question.show_score_tooltip && (
                                                <div className="rounded-[5px] border border-white/10 bg-white/5 px-4 py-3 text-center text-sm font-bold text-white/70">
                                                    {question.score_tooltip_format ||
                                                        "Selected value will be used as raw score."}
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="mx-auto max-w-xl space-y-4">
                                            {groupScaleValues(question).map(
                                                (group, groupIndex) => (
                                                    <div
                                                        key={`scale-group-${groupIndex}`}
                                                        className={[
                                                            "rounded-[5px] border border-white/10 bg-white/5 p-4",
                                                            question.question_type ===
                                                            "linear_scale"
                                                                ? "overflow-x-auto"
                                                                : "",
                                                        ].join(" ")}
                                                    >
                                                        {question.question_type ===
                                                            "divided_scale" && (
                                                            <div className="mb-3 text-center text-xs font-bold uppercase tracking-[0.16em] text-white/50">
                                                                Section{" "}
                                                                {groupIndex + 1}
                                                            </div>
                                                        )}

                                                        <div
                                                            className={
                                                                question.question_type ===
                                                                "linear_scale"
                                                                    ? "flex items-center justify-center gap-3 whitespace-nowrap"
                                                                    : "grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6"
                                                            }
                                                        >
                                                            {group.map(
                                                                (
                                                                    scaleValue,
                                                                ) => {
                                                                    const isSelected =
                                                                        String(
                                                                            data.answer_number,
                                                                        ) ===
                                                                        String(
                                                                            scaleValue,
                                                                        );

                                                                    return (
                                                                        <button
                                                                            key={
                                                                                scaleValue
                                                                            }
                                                                            type="button"
                                                                            onClick={() =>
                                                                                setData(
                                                                                    "answer_number",
                                                                                    String(
                                                                                        scaleValue,
                                                                                    ),
                                                                                )
                                                                            }
                                                                            className={[
                                                                                question.question_type ===
                                                                                "linear_scale"
                                                                                    ? "flex size-10 items-center justify-center rounded-full border-2 text-base font-bold transition-all"
                                                                                    : "rounded-[5px] border-2 px-3 py-3 text-center text-lg font-bold transition-all",
                                                                                isSelected
                                                                                    ? "scale-105 border-[#DB202C] bg-[#DB202C] text-white shadow-[0_0_10px_rgba(219,32,44,0.4)]"
                                                                                    : "border-[#DB202C]/50 bg-transparent text-white/80 hover:border-[#DB202C] hover:bg-[#DB202C]/10",
                                                                            ].join(
                                                                                " ",
                                                                            )}
                                                                        >
                                                                            {
                                                                                scaleValue
                                                                            }
                                                                        </button>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    </div>
                                                ),
                                            )}

                                            <div className="flex items-center justify-between px-2 text-sm font-bold text-white/70">
                                                <span>
                                                    {question.left_label ||
                                                        "Low"}
                                                </span>
                                                <span>
                                                    {question.center_label ||
                                                        "Balanced"}
                                                </span>
                                                <span>
                                                    {question.right_label ||
                                                        "High"}
                                                </span>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="mx-auto mt-6 max-w-2xl space-y-3">
                                    <label className="block text-center text-base font-bold text-white">
                                        Your Answer
                                    </label>

                                    {shouldUseMultilineInput(question) ? (
                                        <Textarea
                                            value={data.answer_text}
                                            onChange={(event) =>
                                                setData(
                                                    "answer_text",
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-24 rounded-[5px] border-white/20 bg-black/40 text-lg font-medium text-white placeholder:text-white/30 focus-visible:ring-[#DB202C]"
                                            placeholder="Type your answer here..."
                                        />
                                    ) : (
                                        <Input
                                            type="text"
                                            value={data.answer_text}
                                            onChange={(event) =>
                                                setData(
                                                    "answer_text",
                                                    event.target.value,
                                                )
                                            }
                                            className="h-12 rounded-[5px] border-white/20 bg-black/40 text-lg font-medium text-white placeholder:text-white/30 focus-visible:ring-[#DB202C]"
                                            placeholder="Type your answer here..."
                                        />
                                    )}

                                    {question.character_limit && (
                                        <div className="text-right text-xs font-bold text-white/50">
                                            {
                                                String(data.answer_text || "")
                                                    .length
                                            }{" "}
                                            / {question.character_limit}{" "}
                                            characters
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="mx-auto max-w-xl space-y-2">
                                {isOptionBased && optionFeedback.message && (
                                    <div
                                        className={[
                                            "text-center text-base font-bold",
                                            optionFeedback.tone === "success"
                                                ? "text-emerald-500"
                                                : "text-[#DB202C]",
                                        ].join(" ")}
                                    >
                                        {optionFeedback.message}
                                    </div>
                                )}

                                {checkboxSelectionLimitMessage(question) && (
                                    <div className="text-center text-sm font-bold text-white/80">
                                        {checkboxSelectionLimitMessage(
                                            question,
                                        )}
                                    </div>
                                )}

                                {selectionFeedback && (
                                    <div className="text-center text-base font-bold text-[#DB202C]">
                                        {selectionFeedback}
                                    </div>
                                )}

                                {Object.keys(errors).length > 0 && (
                                    <div className="text-center text-base font-bold text-[#DB202C]">
                                        {Object.values(errors)[0]}
                                    </div>
                                )}
                            </div>

                            <div className="mt-5 flex flex-col items-center justify-center gap-3 pb-1">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (isOptionBased &&
                                            !canSubmitOptionQuestion)
                                    }
                                    className="h-auto min-w-[150px] w-auto rounded-[5px] bg-[#DB202C] px-8 py-3 text-base font-bold text-white shadow-[0_8px_20px_rgba(219,32,44,0.25)] transition-colors hover:bg-[#c31c28]"
                                >
                                    {isLastQuestion
                                        ? "Submit Assessment"
                                        : "Next"}
                                </Button>

                                {canGoBack && (
                                    <button
                                        type="button"
                                        className="mt-1 text-sm font-bold text-white transition-colors hover:text-white/80"
                                        onClick={() =>
                                            router.post(
                                                route("assessments.back", {
                                                    lesson: lesson.id,
                                                    attempt: attempt.id,
                                                }),
                                            )
                                        }
                                    >
                                        Previous Question
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>

                    {assessment.show_progress_bar && (
                        <div className="mx-auto w-full max-w-lg pt-4">
                            <div className="h-1.5 overflow-hidden rounded-[3px] bg-white/10">
                                <div
                                    className="h-full rounded-[3px] transition-all duration-500 ease-out"
                                    style={{
                                        width: progressWidth,
                                        background: "#DB202C",
                                    }}
                                />
                            </div>
                        </div>
                    )}

                    {assessment.design.footer_content && (
                        <div className="pt-2 pb-2 text-center text-xs font-bold text-white/50">
                            {assessment.design.footer_content}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
