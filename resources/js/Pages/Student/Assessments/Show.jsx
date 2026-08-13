import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";
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
        <AuthenticatedLayout
            studentVariant="immersive"
            studentContentClassName="bg-black"
        >
            <Head title={assessment.title} />

            <main
                className="min-h-[100dvh] w-full bg-black text-white"
                style={{ fontFamily: "'Montserrat', sans-serif" }}
            >
                <div className="mx-auto flex min-h-[100dvh] w-full max-w-[980px] flex-col px-5 pb-8 pt-4 sm:px-8 sm:pb-10 sm:pt-5">
                    <span className="sr-only" aria-live="polite">
                        Time remaining: {remaining}
                    </span>

                    <div className="flex justify-center">
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
                                    className="h-auto w-[170px] object-contain sm:w-[190px]"
                                />
                            </a>
                        ) : (
                            <img
                                src={
                                    assessment.design.logo_url ||
                                    "https://yogafx.b-cdn.net/content/Logo%20YogAFX.png"
                                }
                                alt="YogaFX"
                                className="h-auto w-[170px] object-contain sm:w-[190px]"
                            />
                        )}
                    </div>

                    <div className="mt-10 flex justify-center sm:mt-11">
                        <button
                            type="button"
                            onClick={handleTopBack}
                            className="inline-flex items-center gap-2 bg-transparent px-2 py-1 text-[15px] font-medium uppercase text-white transition-opacity hover:opacity-75 sm:text-[17px]"
                        >
                            <span
                                aria-hidden="true"
                                className="text-[24px] font-light leading-none"
                            >
                                ←
                            </span>
                            <span>Back</span>
                        </button>
                    </div>

                    <div className="mx-auto mt-5 flex w-full max-w-[760px] flex-1 flex-col items-center sm:mt-6">
                        <div className="sr-only">
                            Question {assessment.progress.current} out of{" "}
                            {assessment.progress.total}
                        </div>

                        {question.show_instruction &&
                            question.instruction_text && (
                                <div className="mb-4 max-w-[620px] text-center text-[13px] font-medium leading-5 text-white/75 sm:text-[14px]">
                                    {question.instruction_text}
                                </div>
                            )}

                        <div
                            className="mx-auto max-w-[720px] text-center text-[27px] font-medium leading-[1.45] tracking-[-0.02em] text-white sm:text-[34px]"
                            dangerouslySetInnerHTML={{
                                __html:
                                    question.question_text ||
                                    "No content has been added for this screen yet.",
                            }}
                        />

                        <form
                            onSubmit={submit}
                            className="mt-7 flex w-full flex-1 flex-col items-center sm:mt-8"
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
                                            : "flex max-w-[360px] flex-col gap-[10px]",
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
                                                                "aspect-video w-full rounded-[4px] border-2 object-cover",
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

                            <div className="mx-auto mt-5 w-full max-w-[560px] space-y-2">
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

                            <div className="mt-7 flex w-full justify-center">
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (isOptionBased &&
                                            !canSubmitOptionQuestion)
                                    }
                                    className="h-[52px] min-w-[168px] rounded-[5px] bg-[#ff1111] px-8 text-[17px] font-medium text-white shadow-none transition-colors hover:bg-[#e60000] disabled:cursor-not-allowed disabled:bg-[#ff1111] disabled:opacity-100"
                                >
                                    {isLastQuestion ? "Submit" : "Next"}
                                </Button>
                            </div>

                            {assessment.show_progress_bar && (
                                <div className="mx-auto mt-auto w-full max-w-[300px] pt-16 sm:pt-20">
                                    <div className="mb-2 text-left text-[16px] font-medium text-white sm:text-[17px]">
                                        {progressPercentage}% Complete
                                    </div>

                                    <div className="relative h-[4px] w-full bg-[#d9d9d9]">
                                        <div
                                            className="absolute inset-y-0 left-0 bg-[#ff1717] transition-[width] duration-500 ease-out"
                                            style={{
                                                width: progressWidth,
                                            }}
                                        />
                                    </div>
                                </div>
                            )}
                        </form>
                    </div>

                    {assessment.design.footer_content && (
                        <div className="mt-5 text-center text-[12px] font-medium text-white/55">
                            {assessment.design.footer_content}
                        </div>
                    )}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
