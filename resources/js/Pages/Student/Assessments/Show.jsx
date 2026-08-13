import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
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

    const progressPercentage = Math.max(
        0,
        Math.min(100, Math.round(Number.parseFloat(progressWidth) || 0)),
    );

    const handleTopBack = () => {
        if (canGoBack) {
            router.post(
                route("assessments.back", {
                    lesson: lesson.id,
                    attempt: attempt.id,
                }),
            );

            return;
        }

        router.visit(route("assessments.intro", lesson.id));
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
        <>
            <Head title={assessment.title} />

            <main
                className="min-h-[100dvh] w-full overflow-x-hidden bg-black text-white"
                style={{ fontFamily: "'Montserrat', sans-serif" }}
            >
                <div className="mx-auto flex min-h-[100dvh] w-full max-w-[960px] flex-col px-5 pb-8 pt-4 sm:px-8 sm:pb-10 sm:pt-5">
                    {/* Timer tetap berjalan untuk assessment bertimer,
                        tetapi tidak ditampilkan karena tidak ada pada desain referensi. */}
                    <span className="sr-only" aria-live="polite">
                        Time remaining: {remaining}
                    </span>

                    {/* Logo */}
                    <div className="flex shrink-0 justify-center">
                        {assessment.design.logo_link ? (
                            <a
                                href={assessment.design.logo_link}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center justify-center"
                            >
                                <img
                                    src={
                                        assessment.design.logo_url ||
                                        "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                    }
                                    alt="YogaFX"
                                    className="h-auto w-[178px] object-contain sm:w-[205px]"
                                />
                            </a>
                        ) : (
                            <img
                                src={
                                    assessment.design.logo_url ||
                                    "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                }
                                alt="YogaFX"
                                className="h-auto w-[178px] object-contain sm:w-[205px]"
                            />
                        )}
                    </div>

                    {/* Back */}
                    <div className="mt-8 flex shrink-0 justify-center sm:mt-9">
                        <button
                            type="button"
                            onClick={handleTopBack}
                            className="inline-flex items-center gap-2 bg-transparent px-2 py-1 text-[15px] font-medium uppercase leading-none text-white transition-opacity hover:opacity-75 sm:text-[17px]"
                        >
                            <span
                                aria-hidden="true"
                                className="text-[25px] font-light leading-none"
                            >
                                ←
                            </span>
                            <span>Back</span>
                        </button>
                    </div>

                    {/* Question area */}
                    <div className="mx-auto mt-5 flex w-full max-w-[760px] flex-1 flex-col items-center sm:mt-6">
                        <span className="sr-only">
                            Question {assessment.progress.current} out of{" "}
                            {assessment.progress.total}
                        </span>

                        {question.show_instruction &&
                            question.instruction_text && (
                                <div className="mb-4 max-w-[620px] text-center text-[13px] font-medium leading-5 text-white/75 sm:text-[14px]">
                                    {question.instruction_text}
                                </div>
                            )}

                        <div
                            className="mx-auto max-w-[720px] text-center text-[27px] font-medium leading-[1.42] tracking-[-0.02em] text-white sm:text-[35px]"
                            dangerouslySetInnerHTML={{
                                __html:
                                    question.question_text ||
                                    "No content has been added for this screen yet.",
                            }}
                        />

                        <form
                            onSubmit={submit}
                            className="mt-6 flex w-full flex-1 flex-col items-center sm:mt-7"
                        >
                            {isInfoScreen ? (
                                <div className="mx-auto max-w-[560px] text-center text-[16px] font-medium leading-7 text-white/80">
                                    This screen is informational only.
                                </div>
                            ) : isOptionBased ? (
                                <div
                                    className={[
                                        "mx-auto w-full",
                                        question.question_type ===
                                        "image_button"
                                            ? "grid max-w-[720px] gap-4"
                                            : "flex max-w-[350px] flex-col gap-[8px]",
                                    ].join(" ")}
                                    style={
                                        question.question_type ===
                                        "image_button"
                                            ? {
                                                  gridTemplateColumns: `repeat(${imageColumns}, minmax(0, 1fr))`,
                                              }
                                            : undefined
                                    }
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
                                                    className={[
                                                        "group flex flex-col items-start gap-3 bg-transparent text-left outline-none transition-opacity hover:opacity-90",
                                                        selectedState ===
                                                        "incorrect"
                                                            ? "opacity-80"
                                                            : "",
                                                    ].join(" ")}
                                                >
                                                    {option.image_url && (
                                                        <img
                                                            src={
                                                                option.image_url
                                                            }
                                                            alt={option.label}
                                                            className={[
                                                                "aspect-video w-full rounded-[4px] border-2",
                                                                selected
                                                                    ? "border-[#ff1717]"
                                                                    : "border-transparent",
                                                                getImageFitClass(
                                                                    question,
                                                                ),
                                                            ].join(" ")}
                                                        />
                                                    )}

                                                    {question.show_labels && (
                                                        <div className="flex items-center gap-3 text-[16px] font-medium text-white sm:text-[17px]">
                                                            <span
                                                                className={[
                                                                    "flex size-[22px] shrink-0 items-center justify-center border-[2.5px] border-[#ff1717]",
                                                                    question.allow_multi_select
                                                                        ? "rounded-[3px]"
                                                                        : "rounded-full",
                                                                ].join(" ")}
                                                            >
                                                                {selected ? (
                                                                    question.allow_multi_select ? (
                                                                        <Check className="size-3.5 text-white" />
                                                                    ) : (
                                                                        <span className="size-2.5 rounded-full bg-[#ff1717]" />
                                                                    )
                                                                ) : null}
                                                            </span>

                                                            <span>
                                                                {option.label}
                                                            </span>
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
                                                className="group flex w-full items-center gap-3 bg-transparent py-[5px] text-left outline-none transition-opacity hover:opacity-80 focus-visible:outline-none"
                                            >
                                                <span
                                                    className={[
                                                        "flex size-[22px] shrink-0 items-center justify-center border-[2.5px] border-[#ff1717]",
                                                        question.allow_multi_select
                                                            ? "rounded-[3px]"
                                                            : "rounded-full",
                                                    ].join(" ")}
                                                >
                                                    {selected ? (
                                                        question.allow_multi_select ? (
                                                            <Check className="size-3.5 text-white" />
                                                        ) : (
                                                            <span className="size-2.5 rounded-full bg-[#ff1717]" />
                                                        )
                                                    ) : null}
                                                </span>

                                                <span className="text-[16px] font-medium leading-6 text-white sm:text-[17px]">
                                                    {option.label}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            ) : isNumericBased ? (
                                <div className="mx-auto w-full max-w-[560px]">
                                    {question.question_type === "numeric" ? (
                                        <div className="mx-auto max-w-[320px] space-y-3 text-center">
                                            <label className="block text-[16px] font-medium text-white">
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
                                                className="h-12 rounded-[4px] border border-white/45 bg-black text-center text-[18px] font-medium text-white focus-visible:border-[#ff1717] focus-visible:ring-[#ff1717]/20"
                                            />

                                            <div className="text-[13px] font-medium text-white/60">
                                                Allowed range:{" "}
                                                {question.score_range_min ?? 0}{" "}
                                                to{" "}
                                                {question.score_range_max ?? 0}
                                            </div>
                                        </div>
                                    ) : question.question_type ===
                                      "sliding_scale" ? (
                                        <div className="space-y-4">
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
                                                className="w-full accent-[#ff1717]"
                                            />

                                            <div className="flex items-center justify-between text-[14px] font-medium text-white">
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
                                                <div className="text-center text-[13px] font-medium leading-5 text-white/65">
                                                    {question.score_tooltip_format ||
                                                        "Selected value will be used as raw score."}
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="space-y-5">
                                            {groupScaleValues(question).map(
                                                (group, groupIndex) => (
                                                    <div
                                                        key={`scale-group-${groupIndex}`}
                                                    >
                                                        {question.question_type ===
                                                            "divided_scale" && (
                                                            <div className="mb-3 text-center text-[13px] font-medium uppercase tracking-[0.12em] text-white/65">
                                                                Section{" "}
                                                                {groupIndex + 1}
                                                            </div>
                                                        )}

                                                        <div className="flex flex-wrap items-center justify-center gap-3">
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
                                                                                "flex size-11 items-center justify-center rounded-full border-2 text-[16px] font-medium transition-colors",
                                                                                isSelected
                                                                                    ? "border-[#ff1717] bg-[#ff1717] text-white"
                                                                                    : "border-[#ff1717] bg-black text-white hover:bg-[#ff1717]/10",
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

                                            <div className="flex items-center justify-between text-[14px] font-medium text-white">
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
                                <div className="mx-auto w-full max-w-[560px] space-y-3">
                                    <label className="block text-center text-[16px] font-medium text-white">
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
                                            className="min-h-28 rounded-[4px] border border-white/45 bg-black text-[16px] font-medium text-white placeholder:text-white/35 focus-visible:border-[#ff1717] focus-visible:ring-[#ff1717]/20"
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
                                            className="h-12 rounded-[4px] border border-white/45 bg-black text-[16px] font-medium text-white placeholder:text-white/35 focus-visible:border-[#ff1717] focus-visible:ring-[#ff1717]/20"
                                            placeholder="Type your answer here..."
                                        />
                                    )}

                                    {question.character_limit && (
                                        <div className="text-right text-[12px] font-medium text-white/55">
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

                            <div className="mx-auto mt-4 w-full max-w-[560px] space-y-2">
                                {isOptionBased && optionFeedback.message && (
                                    <div
                                        className={[
                                            "text-center text-[14px] font-semibold",
                                            optionFeedback.tone === "success"
                                                ? "text-emerald-400"
                                                : "text-[#ff5252]",
                                        ].join(" ")}
                                    >
                                        {optionFeedback.message}
                                    </div>
                                )}

                                {checkboxSelectionLimitMessage(question) && (
                                    <div className="text-center text-[13px] font-medium text-white/75">
                                        {checkboxSelectionLimitMessage(
                                            question,
                                        )}
                                    </div>
                                )}

                                {selectionFeedback && (
                                    <div className="text-center text-[14px] font-semibold text-[#ff5252]">
                                        {selectionFeedback}
                                    </div>
                                )}

                                {Object.keys(errors).length > 0 && (
                                    <div className="text-center text-[14px] font-semibold text-[#ff5252]">
                                        {Object.values(errors)[0]}
                                    </div>
                                )}
                            </div>

                            {/* Next */}
                            <div className="mt-6 flex w-full justify-center">
                                <button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (isOptionBased &&
                                            !canSubmitOptionQuestion)
                                    }
                                    className="h-[52px] min-w-[168px] rounded-[5px] bg-[#ff1111] px-8 text-[17px] font-medium text-white transition-colors hover:bg-[#e60000] disabled:cursor-not-allowed disabled:bg-[#ff1111] disabled:opacity-55"
                                >
                                    {isLastQuestion ? "Submit" : "Next"}
                                </button>
                            </div>

                            {/* Progress */}
                            {assessment.show_progress_bar && (
                                <div className="mx-auto mt-auto w-full max-w-[300px] pt-14 sm:pt-16">
                                    <div className="mb-2 text-left text-[16px] font-medium leading-none text-white sm:text-[17px]">
                                        {progressPercentage}% Complete
                                    </div>

                                    <div className="relative h-[4px] w-full bg-[#ff9a9a]">
                                        <div
                                            className="absolute inset-y-0 left-0 bg-[#ff1111] transition-[width] duration-500 ease-out"
                                            style={{
                                                width: progressWidth,
                                            }}
                                        />
                                    </div>
                                </div>
                            )}
                        </form>
                    </div>
                </div>
            </main>
        </>
    );
}
