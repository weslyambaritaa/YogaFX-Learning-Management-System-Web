import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/Components/ui/sheet';
import { Textarea } from '@/Components/ui/textarea';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeInfo,
    Binary,
    Check,
    Circle,
    ChevronRight,
    CircleDot,
    Eye,
    FileQuestion,
    GripVertical,
    ImageIcon,
    LayoutPanelTop,
    ListTree,
    MessageSquareText,
    Palette,
    PenSquare,
    Plus,
    Settings2,
    SlidersHorizontal,
    Sparkles,
    SquareCheckBig,
    Target,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const statusMessages = {
    'scoreboard-question-created': 'Question created.',
    'scoreboard-question-saved': 'Question saved.',
    'scoreboard-question-deleted': 'Question deleted.',
    'scoreboard-option-created': 'Answer option created.',
    'scoreboard-option-saved': 'Answer option saved.',
    'scoreboard-option-deleted': 'Answer option deleted.',
    'scoreboard-design-saved': 'Design settings saved.',
    'scoreboard-result-range-created': 'Result range created.',
    'scoreboard-result-range-saved': 'Result range saved.',
    'scoreboard-result-range-deleted': 'Result range deleted.',
    'scoreboard-created': 'Scoreboard created.',
};

const optionBasedTypes = [
    'yes_no_maybe',
    'multiple_choice_checkboxes',
    'multiple_choice_buttons',
    'radio_buttons',
    'image_button',
];

const scaleTypes = ['sliding_scale', 'linear_scale', 'divided_scale'];
const numericTypes = [...scaleTypes, 'numeric'];
const answerTabScoringCategoryTypes = [
    'sliding_scale',
    'linear_scale',
    'divided_scale',
    'numeric',
    'open_text',
    'info_screen',
];

function questionTypeSupportsAnswerJump(type) {
    return [
        'yes_no_maybe',
        'multiple_choice_buttons',
        'radio_buttons',
        'image_button',
    ].includes(type);
}

function questionTypeSupportsCustomAnswerRows(type) {
    return [
        'multiple_choice_buttons',
        'multiple_choice_checkboxes',
        'radio_buttons',
        'image_button',
    ].includes(type);
}

function questionTypeUsesAnswerTabScoringCategory(type) {
    return answerTabScoringCategoryTypes.includes(type);
}

function questionTypeSupportsSingleCorrectSelection(type, allowMultiSelect = false) {
    if (type === 'yes_no_maybe' || type === 'radio_buttons') {
        return true;
    }

    if (type === 'multiple_choice_buttons' || type === 'image_button') {
        return !allowMultiSelect;
    }

    return false;
}

function questionTypeSupportsMultipleCorrectSelection(
    type,
    allowMultiSelect = false,
) {
    if (!optionBasedTypes.includes(type)) {
        return false;
    }

    return !questionTypeSupportsSingleCorrectSelection(type, allowMultiSelect);
}

function fieldError(errors, keys) {
    return keys.map((key) => errors[key]).find(Boolean);
}

function serializeDraft(data) {
    return JSON.stringify(data);
}

function builderDraftStorageKey(scoreboardId) {
    return `scoreboard-builder-drafts:${scoreboardId}`;
}

function sanitizeDraftValue(value) {
    if (value instanceof File) {
        return null;
    }

    if (Array.isArray(value)) {
        return value.map((item) => sanitizeDraftValue(item));
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([key, item]) => [key, sanitizeDraftValue(item)]),
        );
    }

    return value;
}

function readBuilderDraftCache(scoreboardId) {
    if (typeof window === 'undefined') {
        return null;
    }

    const raw = window.localStorage.getItem(builderDraftStorageKey(scoreboardId));

    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch (error) {
        window.localStorage.removeItem(builderDraftStorageKey(scoreboardId));

        return null;
    }
}

function writeBuilderDraftCache(scoreboardId, payload) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        builderDraftStorageKey(scoreboardId),
        JSON.stringify(sanitizeDraftValue(payload)),
    );
}

function clearBuilderDraftCache(scoreboardId) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.removeItem(builderDraftStorageKey(scoreboardId));
}

function omitKey(collection, keyToOmit) {
    return Object.fromEntries(
        Object.entries(collection).filter(([key]) => String(key) !== String(keyToOmit)),
    );
}

function omitKeys(collection, keysToOmit) {
    const keySet = new Set(keysToOmit.map((key) => String(key)));

    return Object.fromEntries(
        Object.entries(collection).filter(([key]) => !keySet.has(String(key))),
    );
}

function formatQuestionType(type) {
    return type
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function buildQuestionFormData(question) {
    return {
        title: question?.title ?? '',
        question_text: question?.question_text ?? '',
        question_type: question?.question_type ?? 'radio_buttons',
        sort_order: question?.sort_order ?? 1,
        show_instruction: Boolean(question?.show_instruction),
        instruction_text: question?.instruction_text ?? '',
        required: Boolean(question?.required),
        randomize_answers_order: Boolean(question?.randomize_answers_order),
        jump_enabled: Boolean(question?.jump_enabled),
        jump_to_question_id: question?.jump_to_question_id ?? '',
        show_maybe_answer: false,
        allow_multi_select: Boolean(question?.allow_multi_select),
        min_count: question?.min_count ?? '',
        max_count: question?.max_count ?? '',
        allow_other_option: Boolean(question?.allow_other_option),
        show_labels: Boolean(question?.show_labels ?? true),
        score_range_min: question?.score_range_min ?? '',
        score_range_max: question?.score_range_max ?? '',
        starting_score: question?.starting_score ?? '',
        section_count: question?.section_count ?? '',
        allow_decimals: Boolean(question?.allow_decimals),
        input_type:
            question?.input_type === 'textarea' || question?.input_type === 'multi_line'
                ? 'multi_line'
                : 'single_line',
        character_limit: question?.character_limit ?? '',
        show_score_tooltip: Boolean(question?.show_score_tooltip),
        score_tooltip_format: question?.score_tooltip_format ?? '',
        answer_image_fit: question?.answer_image_fit ?? 'cover',
        answers_per_row: Number(question?.answers_per_row) === 4 ? 4 : 2,
        scoring_category: question?.scoring_category ?? 'overall_only',
        left_label: question?.left_label ?? '',
        center_label: question?.center_label ?? '',
        right_label: question?.right_label ?? '',
    };
}

function shouldUseMultilineInput(inputType, characterLimit) {
    return inputType === 'multi_line' || Number(characterLimit || 0) > 140;
}

function getScaleBounds(values) {
    const min = Number.isFinite(Number(values.score_range_min))
        ? Number(values.score_range_min)
        : 1;
    const max = Number.isFinite(Number(values.score_range_max))
        ? Number(values.score_range_max)
        : Math.max(min, 5);

    return {
        min: Math.trunc(min),
        max: Math.max(Math.trunc(max), Math.trunc(min)),
    };
}

function getIntegerScaleValues(values) {
    const { min, max } = getScaleBounds(values);

    return Array.from({ length: max - min + 1 }, (_, index) => min + index);
}

function groupScaleValues(scaleValues, sectionCount) {
    const totalSections = Math.max(1, Number(sectionCount || 1));
    const groups = [];
    const valuesPerGroup = Math.ceil(scaleValues.length / totalSections);

    for (let index = 0; index < scaleValues.length; index += valuesPerGroup) {
        groups.push(scaleValues.slice(index, index + valuesPerGroup));
    }

    return groups;
}

function getImageFitClass(answerImageFit) {
    return answerImageFit === 'contain' ? 'object-contain' : 'object-cover';
}

function buildVirtualYesNoOptions() {
    return [
        {
            id: 'virtual-yes-option',
            label: 'Yes',
            internal_value: 'yes',
            sort_order: 1,
            is_correct: false,
            is_virtual: true,
            image_url: null,
        },
        {
            id: 'virtual-no-option',
            label: 'No',
            internal_value: 'no',
            sort_order: 2,
            is_correct: false,
            is_virtual: true,
            image_url: null,
        },
    ];
}

function buildOptionDraft(option) {
    return {
        label: option?.label ?? '',
        internal_value: option?.internal_value ?? '',
        image: null,
        sort_order: option?.sort_order ?? 1,
        is_correct: Boolean(option?.is_correct),
        scoring_enabled: Boolean(option?.scoring_enabled),
        score_value: option?.score_value ?? '',
        jump_enabled: Boolean(option?.jump_enabled),
        jump_to_question_id: option?.jump_to_question_id ?? '',
        is_other_option: Boolean(option?.is_other_option),
    };
}

function getNavigatorLabel(question, index) {
    return (
        question.title?.trim() ||
        question.question_text?.trim() ||
        `Question ${index + 1}`
    );
}

function questionSupportsCenterAnswerManager(type) {
    return optionBasedTypes.includes(type);
}

function getOptionSortOrder(option, optionDrafts) {
    return Number(optionDrafts[option.id]?.sort_order ?? option.sort_order ?? 1);
}

function getQuestionSortOrder(question, questionDrafts) {
    return Number(questionDrafts[question.id]?.sort_order ?? question.sort_order ?? 1);
}

function getQuestionTypeVisual(type) {
    switch (type) {
        case 'yes_no_maybe':
        case 'radio_buttons':
            return { icon: CircleDot, label: 'Choice' };
        case 'multiple_choice_buttons':
        case 'multiple_choice_checkboxes':
            return { icon: SquareCheckBig, label: 'Multi Select' };
        case 'image_button':
            return { icon: ImageIcon, label: 'Image Choice' };
        case 'sliding_scale':
        case 'linear_scale':
        case 'divided_scale':
            return { icon: SlidersHorizontal, label: 'Scale' };
        case 'numeric':
            return { icon: Binary, label: 'Numeric' };
        case 'open_text':
            return { icon: MessageSquareText, label: 'Open Text' };
        case 'info_screen':
            return { icon: BadgeInfo, label: 'Info Screen' };
        default:
            return { icon: FileQuestion, label: formatQuestionType(type) };
    }
}

/* ============================================================
   LEFT PANEL — Question navigator
   Simplified: single flat list, no extra wrapper card per item
   beyond what's needed to show drag/active/drop state.
   ============================================================ */
function ScoreboardNavigator({
    scoreboardId,
    questions,
    selectedQuestionId,
    draggedQuestionId,
    dropTargetQuestionId,
    startQuestionDrag,
    markQuestionDropTarget,
    dropQuestionAt,
    finishQuestionDrag,
}) {
    return (
        <section className="flex min-h-0 flex-col overflow-hidden rounded-[5px] border border-slate-200 bg-white xl:sticky xl:top-6 xl:h-[calc(100vh-12rem)]">
            <header className="flex items-center justify-between gap-3 border-b border-slate-200 px-2.5 py-2">
                <div className="min-w-0">
                    <div className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                        <ListTree className="size-3.5" />
                        Screens
                    </div>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {questions.length} question{questions.length === 1 ? '' : 's'}
                    </p>
                </div>

                <Button
                    size="sm"
                    className="shrink-0 rounded-[5px] px-2.5 py-2"
                    onClick={() =>
                        router.post(
                            route('admin.scoreboards.questions.store', scoreboardId),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Plus className="size-4" />
                    Add
                </Button>
            </header>

            <div className="min-h-0 space-y-1.5 overflow-y-auto p-2.5 xl:flex-1">
                {questions.length > 0 ? (
                    questions.map((question, index) => {
                        const active = question.id === selectedQuestionId;
                        const typeVisual = getQuestionTypeVisual(
                            question.question_type,
                        );
                        const TypeIcon = typeVisual.icon;

                        return (
                            <div
                                key={question.id}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    markQuestionDropTarget?.(question.id);
                                }}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    dropQuestionAt?.(question.id);
                                }}
                                className={[
                                    'rounded-[5px] border transition',
                                    dropTargetQuestionId === question.id
                                        ? 'border-[#203529] ring-2 ring-[#203529]/10'
                                        : draggedQuestionId === question.id
                                          ? 'border-slate-300 opacity-70'
                                          : active
                                            ? 'border-[#203529] bg-[#203529] text-white'
                                            : 'border-transparent text-slate-900 hover:bg-slate-50',
                                ].join(' ')}
                            >
                                <div className="flex items-center gap-2.5 px-2.5 py-2">
                                    <button
                                        type="button"
                                        draggable
                                        onDragStart={(event) => {
                                            event.dataTransfer.effectAllowed = 'move';
                                            startQuestionDrag?.(question.id);
                                        }}
                                        onDragEnd={() => finishQuestionDrag?.()}
                                        className={[
                                            'flex size-7 shrink-0 cursor-grab items-center justify-center rounded-[5px] transition',
                                            active
                                                ? 'text-white/70 hover:text-white'
                                                : 'text-slate-300 hover:text-slate-500',
                                        ].join(' ')}
                                        aria-label="Reorder question"
                                    >
                                        <GripVertical className="size-4" />
                                    </button>

                                    <Link
                                        href={route('admin.scoreboards.builder', {
                                            assessment: scoreboardId,
                                            question: question.id,
                                        })}
                                        preserveScroll
                                        preserveState
                                        className="flex min-w-0 flex-1 items-center gap-2.5"
                                    >
                                        <div
                                            className={[
                                                'flex size-7 shrink-0 items-center justify-center rounded-[5px] text-xs font-semibold',
                                                active
                                                    ? 'bg-white/15 text-white'
                                                    : 'bg-slate-100 text-slate-600',
                                            ].join(' ')}
                                        >
                                            {question.sort_order}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-sm font-medium leading-5">
                                                {getNavigatorLabel(question, index)}
                                            </div>
                                            <div
                                                className={[
                                                    'mt-0.5 flex items-center gap-1 text-[11px]',
                                                    active ? 'text-white/70' : 'text-slate-400',
                                                ].join(' ')}
                                            >
                                                <TypeIcon className="size-3" />
                                                {typeVisual.label}
                                            </div>
                                        </div>

                                        <ChevronRight
                                            className={[
                                                'size-4 shrink-0',
                                                active ? 'text-white/60' : 'text-slate-300',
                                            ].join(' ')}
                                        />
                                    </Link>
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-center text-sm text-slate-500">
                        Add the first question to start the builder flow.
                    </div>
                )}
            </div>
        </section>
    );
}

function InlineTextBlock({
    label,
    value,
    onChange,
    placeholder,
    multiline = false,
    dominant = false,
}) {
    const baseClass = [
        'w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-slate-900 shadow-none outline-none ring-0 transition placeholder:text-slate-400',
        'focus:border-slate-300 focus:bg-white focus:shadow-none focus:outline-none focus:ring-0',
        'focus-visible:border-slate-300 focus-visible:bg-white focus-visible:shadow-none focus-visible:outline-none focus-visible:ring-0 focus-visible:ring-offset-0',
        dominant
            ? 'min-h-[30px] text-[1.85rem] font-semibold leading-[1.2] tracking-tight'
            : 'text-sm font-medium',
    ].join(' ');
    
    return (
        <div className="space-y-1.5">
            <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                {label}
            </div>
            {multiline ? (
                <Textarea
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    className={baseClass + ' resize-none'}
                />
            ) : (
                <Input
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    className={baseClass}
                />
            )}
        </div>
    );
}

/* ============================================================
   TOP BAR — Title, status, actions
   Simplified: one info strip instead of two side-by-side boxes,
   actions grouped (primary Save + icon-style secondary actions).
   ============================================================ */
function BuilderWorkspaceBar({
    scoreboard,
    selectedQuestion,
    resultRanges,
    design,
    mobilePanel,
    saveAllChanges,
    saveState,
    hasUnsavedChanges,
}) {
    return (
        <section className="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div className="flex flex-col gap-4 px-2.5 py-2 lg:flex-row lg:items-start lg:justify-between lg:px-6">
                <div className="min-w-0 space-y-2.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{scoreboard.status}</Badge>
                        <Badge variant={scoreboard.is_active ? 'secondary' : 'outline'}>
                            {scoreboard.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                        {selectedQuestion ? (
                            <Badge variant="outline">
                                Screen {selectedQuestion.sort_order}
                            </Badge>
                        ) : null}
                    </div>

                    <div>
                        <h2 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {scoreboard.title}
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-slate-500">
                            {selectedQuestion
                                ? 'Edit text directly on the canvas, then save from the configuration panel.'
                                : 'Add a question to start shaping the builder flow.'}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 lg:shrink-0">
                    <span className="rounded-[5px] border border-slate-200 px-2.5 py-2 text-xs font-medium text-slate-500">
                        {saveState === 'saving'
                            ? 'Saving…'
                            : hasUnsavedChanges
                              ? 'Unsaved changes'
                              : 'Saved'}
                    </span>

                    <Button
                        type="button"
                        onClick={saveAllChanges}
                        disabled={saveState === 'saving' || !hasUnsavedChanges}
                        className="rounded-[5px] bg-[#203529] px-2.5 py-2 text-white hover:bg-[#18281f] disabled:bg-slate-200 disabled:text-slate-500"
                    >
                        Save
                    </Button>

                    <div className="flex items-center gap-1 rounded-[5px] border border-slate-200 p-1">
                        <ResultRangesDrawer
                            scoreboardId={scoreboard.id}
                            resultRanges={resultRanges}
                        />
                        <DesignSettingsDrawer
                            scoreboardId={scoreboard.id}
                            design={design}
                        />
                        <Button asChild variant="ghost" size="sm" className="rounded-[5px]">
                            <Link href={route('admin.scoreboards.edit', scoreboard.id)}>
                                <LayoutPanelTop className="size-4" />
                                <span className="hidden sm:inline">Edit Meta</span>
                            </Link>
                        </Button>
                    </div>

                    <Button asChild variant="outline" size="sm">
                        <Link href={route('admin.scoreboards.index')}>
                            <ArrowLeft className="size-4" />
                            <span className="hidden sm:inline">Back</span>
                        </Link>
                    </Button>

                    <Sheet>
                        <SheetTrigger asChild>
                            <Button size="sm" className="xl:hidden">
                                <Settings2 className="size-4" />
                                Panel
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            className="w-full overflow-y-auto sm:max-w-xl"
                        >
                            <SheetHeader>
                                <SheetTitle>Builder Panel</SheetTitle>
                                <SheetDescription>
                                    Question and Answers settings for the active screen.
                                </SheetDescription>
                            </SheetHeader>
                            <div className="mt-6">{mobilePanel}</div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </section>
    );
}

function PreviewOptionGrid({ question, values, optionDrafts }) {
    const previewColumns = Math.min(Math.max(Number(values.answers_per_row || 2), 1), 4);
    const options = question.options ?? [];

    if (values.question_type === 'info_screen') {
        return (
            <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm leading-7 text-slate-500">
                This screen presents information only. Students continue through the flow without storing an answer.
            </div>
        );
    }

    if (!optionBasedTypes.includes(values.question_type)) {
        if (scaleTypes.includes(values.question_type)) {
            const scaleValues = getIntegerScaleValues(values);
            const isLinearScale = values.question_type === 'linear_scale';
            const isDividedScale = values.question_type === 'divided_scale';
            const groupedScaleValues = isDividedScale
                ? groupScaleValues(scaleValues, values.section_count)
                : [scaleValues];

            return (
                <div className="space-y-4">
                    <div className={isLinearScale ? 'overflow-x-auto' : 'space-y-3'}>
                        {groupedScaleValues.map((group, groupIndex) => (
                            <div
                                key={`scale-group-${groupIndex}`}
                                className={[
                                    'rounded-[5px] bg-slate-50 p-4',
                                    isLinearScale
                                        ? 'inline-flex min-w-full items-center gap-2 whitespace-nowrap'
                                        : '',
                                    isDividedScale ? 'space-y-3' : '',
                                ].join(' ')}
                            >
                                {isDividedScale && (
                                    <div className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">
                                        Section {groupIndex + 1}
                                    </div>
                                )}
                                <div
                                    className={
                                        isLinearScale
                                            ? 'flex items-center gap-2'
                                            : 'grid grid-cols-2 gap-2.5 md:grid-cols-4 xl:grid-cols-6'
                                    }
                                >
                                    {group.map((scaleValue) => (
                                        <div
                                            key={scaleValue}
                                            className={
                                                isLinearScale
                                                    ? 'flex size-9 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-700'
                                                    : 'rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-center text-sm font-medium text-slate-700'
                                            }
                                        >
                                            {scaleValue}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="flex items-center justify-between gap-3 text-sm text-slate-500">
                        <span>{values.left_label || 'Low'}</span>
                        <span>{values.center_label || 'Balanced'}</span>
                        <span>{values.right_label || 'High'}</span>
                    </div>
                    {values.show_score_tooltip && (
                        <div className="rounded-[5px] bg-slate-50 px-2.5 py-2 text-sm text-slate-600">
                            {values.score_tooltip_format ||
                                'Selected value will be used as raw score.'}
                        </div>
                    )}
                </div>
            );
        }

        if (values.question_type === 'numeric') {
            return (
                <div>
                    <div className="text-sm font-medium text-slate-700">Student Answer</div>
                    <div className="mt-2 rounded-[5px] border border-slate-200 bg-slate-50 px-2.5 py-2 text-sm text-slate-400">
                        Numeric input field
                    </div>
                    <div className="mt-2 text-xs text-slate-500">
                        Range: {values.score_range_min || 0} to {values.score_range_max || 0}
                    </div>
                </div>
            );
        }

        return (
            <div>
                <div className="text-sm font-medium text-slate-700">Student Answer</div>
                <div className="mt-2 rounded-[5px] border border-slate-200 bg-slate-50 px-2.5 py-2 text-sm text-slate-400">
                    {shouldUseMultilineInput(values.input_type, values.character_limit)
                        ? 'Long text answer field'
                        : 'Single-line text answer field'}
                </div>
                {values.character_limit ? (
                    <div className="mt-2 text-xs text-slate-500">
                        Character limit: {values.character_limit}
                    </div>
                ) : null}
            </div>
        );
    }

    const visibleOptions =
        values.question_type === 'yes_no_maybe'
            ? (() => {
                const fixedOptions = options.filter((option) =>
                    ['yes', 'no'].includes(option.internal_value),
                );

                return fixedOptions.length > 0
                    ? fixedOptions
                    : buildVirtualYesNoOptions();
            })()
            : options;

    return (
        <div
            className="grid gap-2.5"
            style={{
                gridTemplateColumns:
                    values.question_type === 'image_button'
                        ? `repeat(${previewColumns}, minmax(0, 1fr))`
                        : 'repeat(1, minmax(0, 1fr))',
            }}
        >
            {visibleOptions.length > 0 ? (
                visibleOptions.map((option) => (
                    <div
                        key={option.id}
                        className={[
                            'rounded-[5px] border border-slate-200 bg-white text-slate-800',
                            values.question_type === 'image_button'
                                ? 'overflow-hidden'
                                : 'px-2.5 py-2',
                        ].join(' ')}
                    >
                        {values.question_type === 'image_button' && (
                            <>
                                <div className="flex h-32 items-center justify-center bg-slate-100">
                                    {option.image_url ? (
                                        <img
                                            src={option.image_url}
                                            alt={option.label}
                                            className={`h-full w-full ${getImageFitClass(values.answer_image_fit)}`}
                                        />
                                    ) : (
                                        <div className="text-xs font-medium uppercase tracking-[0.12em] text-slate-400">
                                            Image
                                        </div>
                                    )}
                                </div>
                                {values.show_labels && (
                                    <div className="px-2.5 py-2 text-sm font-medium text-slate-900">
                                        {optionDrafts[option.id]?.label ?? option.label}
                                    </div>
                                )}
                            </>
                        )}

                        {values.question_type !== 'image_button' && (
                            <div className="text-sm font-medium text-slate-900">
                                {optionDrafts[option.id]?.label ?? option.label}
                            </div>
                        )}
                    </div>
                ))
            ) : (
                <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-500">
                    Add answers in the Answers tab to complete this screen preview.
                </div>
            )}
        </div>
    );
}

function CenterCorrectControl({ checked, multiple, onChange, disabled = false }) {
    return (
        <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-[5px] border border-slate-200 px-2.5 py-2 text-xs font-semibold text-slate-700">
            <input
                type={multiple ? 'checkbox' : 'radio'}
                checked={checked}
                disabled={disabled}
                onChange={onChange}
                className="size-4 accent-[#203529]"
            />
            <span className="inline-flex items-center gap-1.5">
                {checked ? (
                    <Check className="size-3.5 text-emerald-600" />
                ) : (
                    <Circle className="size-3.5 text-slate-400" />
                )}
                Correct
            </span>
        </label>
    );
}

function CenterAnswerRow({
    scoreboardId,
    question,
    option,
    draft,
    setOptionValue,
    toggleCorrectOption,
    allowMultipleCorrect,
    draggable = false,
    isDragActive = false,
    isDropTarget = false,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}) {
    const data = draft;

    const updateField = (field, value) => {
        setOptionValue(option.id, field, value);
    };

    const labelValue = data.label;

    return (
        <div
            onDragOver={(event) => {
                if (!draggable) {
                    return;
                }

                event.preventDefault();
                onDragOver?.();
            }}
            onDrop={(event) => {
                if (!draggable) {
                    return;
                }

                event.preventDefault();
                onDrop?.();
            }}
            className={[
                'rounded-[5px] border bg-white px-2.5 py-2 transition',
                isDropTarget
                    ? 'border-[#203529] ring-2 ring-[#203529]/10'
                    : isDragActive
                      ? 'border-slate-300 opacity-70'
                      : 'border-slate-200',
            ].join(' ')}
        >
            <div className="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    draggable={draggable}
                    onDragStart={(event) => {
                        if (!draggable) {
                            return;
                        }

                        event.dataTransfer.effectAllowed = 'move';
                        onDragStart?.();
                    }}
                    onDragEnd={() => onDragEnd?.()}
                    className={[
                        'flex size-8 shrink-0 items-center justify-center rounded-[5px] text-slate-300 transition',
                        draggable
                            ? 'cursor-grab hover:text-slate-500'
                            : 'cursor-default opacity-40',
                    ].join(' ')}
                    aria-label="Reorder answer"
                >
                    <GripVertical className="size-4" />
                </button>

                <div className="flex size-8 shrink-0 items-center justify-center rounded-[5px] bg-slate-100 text-xs font-semibold text-slate-600">
                    {data.sort_order}
                </div>

                {question.question_type === 'image_button' ? (
                    <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-[5px] bg-slate-100">
                        {option.image_url ? (
                            <img
                                src={option.image_url}
                                alt={labelValue || option.label}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <ImageIcon className="size-4 text-slate-400" />
                        )}
                    </div>
                ) : null}

                <div className="min-w-0 flex-1">
                    {option.is_fixed_option ? (
                        <div className="min-h-10 rounded-[5px] bg-slate-50 px-2.5 py-2 text-sm font-medium text-slate-900">
                            {labelValue}
                        </div>
                    ) : (
                        <Input
                            value={labelValue}
                            onChange={(event) =>
                                updateField('label', event.target.value)
                            }
                            placeholder="Answer label"
                            className="min-h-10 rounded-[5px] border-slate-200"
                        />
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-2">
                    <CenterCorrectControl
                        checked={data.is_correct}
                        multiple={allowMultipleCorrect}
                        onChange={() => toggleCorrectOption(option)}
                    />

                    {!option.is_fixed_option ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                            onClick={() =>
                                router.delete(
                                    route('admin.scoreboards.options.destroy', {
                                        assessment: scoreboardId,
                                        question: question.id,
                                        option: option.id,
                                    }),
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Delete
                        </Button>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

/* ============================================================
   CENTER PANEL — Canvas / live editor preview
   Simplified: removed nested decorative wrappers (background
   frame, backdrop-blur frame, instruction box). Single card,
   single padded content column.
   ============================================================ */
function BuilderCanvas({
    scoreboard,
    question,
    values,
    visibleOptions,
    setValue,
    error,
    design,
    optionDrafts,
    setOptionValue,
    toggleCorrectOption,
    draggedOptionId,
    dropTargetOptionId,
    startOptionDrag,
    markOptionDropTarget,
    dropOptionAt,
    finishOptionDrag,
}) {
    const supportsMultipleCorrectAnswers = question
        ? questionTypeSupportsMultipleCorrectSelection(
              values.question_type,
              values.allow_multi_select,
          )
        : false;
    const supportsCenterAnswers = question
        ? questionSupportsCenterAnswerManager(values.question_type)
        : false;

    return (
        <section className="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-2.5 py-2">
                <div className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <Eye className="size-4 text-slate-400" />
                    Editor Preview
                </div>

                <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    {question ? (
                        <span className="rounded-[5px] bg-slate-100 px-2.5 py-2 font-medium">
                            Screen {question.sort_order}
                        </span>
                    ) : null}
                    <span className="rounded-[5px] bg-slate-100 px-2.5 py-2 font-medium">
                        Student Preview
                    </span>
                    {question ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="rounded-[5px] px-2.5 py-2 text-xs"
                            onClick={() =>
                                router.post(
                                    route('admin.scoreboards.questions.store', scoreboard.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Plus className="size-3.5" />
                            Add Question
                        </Button>
                    ) : null}
                </div>
            </header>

            {question ? (
                <div
                    className="p-5 md:p-8"
                    style={{
                        background:
                            design.section_background ||
                            'linear-gradient(180deg, rgba(250,250,248,1) 0%, rgba(255,255,255,1) 100%)',
                        paddingTop: design.top_margin || undefined,
                        paddingBottom: design.bottom_margin || undefined,
                    }}
                >
                    <div className="mx-auto max-w-2xl space-y-7">
                        <InlineTextBlock
                            label="Screen Label"
                            value={values.title}
                            onChange={(event) => setValue('title', event.target.value)}
                            placeholder="Optional internal screen title"
                        />

                        {values.show_instruction ? (
                            <div className="border-l-2 border-slate-200 pl-4">
                                <InlineTextBlock
                                    label="Instruction"
                                    value={values.instruction_text}
                                    onChange={(event) =>
                                        setValue('instruction_text', event.target.value)
                                    }
                                    placeholder="Add optional guidance for the student."
                                    multiline
                                />
                            </div>
                        ) : null}

                        <InlineTextBlock
                            label="Question Text"
                            value={values.question_text}
                            onChange={(event) =>
                                setValue('question_text', event.target.value)
                            }
                            placeholder="Click here and write the main question students will see."
                            multiline
                            dominant
                        />

                        {!supportsCenterAnswers ? (
                            <PreviewOptionGrid
                                question={question}
                                values={values}
                                optionDrafts={optionDrafts}
                            />
                        ) : null}

                        {supportsCenterAnswers ? (
                            <div className="space-y-3">
                                <div>
                                    <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                                        Answers
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {supportsMultipleCorrectAnswers
                                            ? 'Edit labels and choose one or more correct answers directly here.'
                                            : 'Edit labels and choose the correct answer directly here.'}
                                    </p>
                                </div>

                                <div className="space-y-2.5">
                                    {visibleOptions.map((option) =>
                                        option.is_virtual ? (
                                            <div
                                                key={option.id}
                                                className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-500"
                                            >
                                                <div className="font-medium text-slate-900">
                                                    Other
                                                </div>
                                                <div className="mt-1 leading-6">
                                                    This special answer row will be created after you save the question.
                                                </div>
                                            </div>
                                        ) : (
                                            <CenterAnswerRow
                                                key={`center-answer-${option.id}`}
                                                scoreboardId={scoreboard.id}
                                                question={question}
                                                option={option}
                                                draft={
                                                    optionDrafts[option.id] ??
                                                    buildOptionDraft(option)
                                                }
                                                setOptionValue={setOptionValue}
                                                toggleCorrectOption={toggleCorrectOption}
                                                allowMultipleCorrect={
                                                    supportsMultipleCorrectAnswers
                                                }
                                                draggable={!option.is_virtual}
                                                isDragActive={
                                                    draggedOptionId === option.id
                                                }
                                                isDropTarget={
                                                    dropTargetOptionId === option.id
                                                }
                                                onDragStart={() =>
                                                    startOptionDrag(option.id)
                                                }
                                                onDragOver={() =>
                                                    markOptionDropTarget(option.id)
                                                }
                                                onDrop={() => dropOptionAt(option.id)}
                                                onDragEnd={finishOptionDrag}
                                            />
                                        ),
                                    )}
                                </div>

                                {questionTypeSupportsCustomAnswerRows(
                                    values.question_type,
                                ) ? (
                                    <div className="flex justify-end pt-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    route('admin.scoreboards.options.store', {
                                                        assessment: scoreboard.id,
                                                        question: question.id,
                                                    }),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Plus className="size-4" />
                                            Add Answer
                                        </Button>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        <div className="flex items-center justify-end border-t border-slate-100 pt-6">
                            <button
                                type="button"
                                className="inline-flex items-center justify-center rounded-[5px] bg-[#203529] px-2.5 py-2 text-sm font-semibold text-white"
                            >
                                Next
                            </button>
                        </div>

                        {design.footer_content ? (
                            <div className="border-t border-slate-100 pt-5 text-sm leading-7 text-slate-500">
                                {design.footer_content}
                            </div>
                        ) : null}
                    </div>

                    {error ? (
                        <div className="mx-auto mt-5 max-w-2xl rounded-[5px] border border-rose-200 bg-rose-50 px-2.5 py-2 text-sm text-rose-900">
                            {error}
                        </div>
                    ) : null}
                </div>
            ) : (
                <div className="px-2.5 py-16 text-center">
                    <div className="mx-auto max-w-md">
                        <div className="inline-flex rounded-[5px] bg-slate-100 p-3 text-slate-400">
                            <Sparkles className="size-5" />
                        </div>
                        <h3 className="mt-4 text-lg font-semibold text-slate-900">
                            Start building the first screen
                        </h3>
                        <p className="mt-2 text-sm leading-6 text-slate-500">
                            Add a question from the left panel and the canvas will turn into your live editing surface.
                        </p>
                    </div>
                </div>
            )}
        </section>
    );
}

/* ============================================================
   RIGHT PANEL — Question / Answers configuration
   Simplified: section "cards" replaced with plain labeled
   groups separated by divide-y, no border box per group.
   ============================================================ */
function QuestionSection({ title, description, children }) {
    return (
        <div className="space-y-3 py-2 first:pt-0">
            <div>
                <h4 className="text-[13px] font-semibold text-slate-900">{title}</h4>
                {description ? (
                    <p className="mt-0.5 text-xs leading-5 text-slate-500">
                        {description}
                    </p>
                ) : null}
            </div>
            <div className="space-y-3">{children}</div>
        </div>
    );
}

function ToggleField({ checked, onChange, label, description }) {
    return (
        <label className="flex w-full items-start justify-between gap-3 rounded-[5px] border border-slate-200 px-2.5 py-2">
            <div className="min-w-0 flex-1">
                <div className="break-words text-sm font-medium leading-5 text-slate-900">
                    {label}
                </div>
                {description ? (
                    <div className="mt-1 text-xs leading-5 text-slate-500">
                        {description}
                    </div>
                ) : null}
            </div>
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="peer sr-only"
            />
            <div className="relative mt-0.5 h-6 w-11 shrink-0 rounded-[5px] bg-slate-200 transition peer-checked:bg-[#203529]">
                <span className="absolute left-1 top-1 size-4 rounded-[5px] bg-white shadow-sm transition peer-checked:left-6" />
            </div>
        </label>
    );
}

function AnswerRow({
    scoreboardId,
    question,
    option,
    questionTargets,
    draft,
    setOptionValue,
    draggable = false,
    isDragActive = false,
    isDropTarget = false,
    onDragStart,
    onDragOver,
    onDrop,
    onDragEnd,
}) {
    const [expanded, setExpanded] = useState(false);
    const imageInputRef = useRef(null);
    const [localImagePreviewUrl, setLocalImagePreviewUrl] = useState(null);
    const { data, setData, patch, processing, errors } = useForm(draft);

    useEffect(() => {
        setData(draft);
    }, [draft, option.id]);

    useEffect(() => {
        if (!(data.image instanceof File)) {
            setLocalImagePreviewUrl(null);
            return undefined;
        }

        const nextPreviewUrl = URL.createObjectURL(data.image);
        setLocalImagePreviewUrl(nextPreviewUrl);

        return () => {
            URL.revokeObjectURL(nextPreviewUrl);
        };
    }, [data.image]);

    const updateField = (field, value) => {
        setData(field, value);
        setOptionValue(option.id, field, value);
    };

    const submit = (event) => {
        event.preventDefault();
        patch(
            route('admin.scoreboards.options.update', {
                assessment: scoreboardId,
                question: question.id,
                option: option.id,
            }),
            { preserveScroll: true, forceFormData: true },
        );
    };

    const supportsJump = questionTypeSupportsAnswerJump(question.question_type);
    const supportsImage = question.question_type === 'image_button';
    const imagePreviewUrl = localImagePreviewUrl || option.image_url || null;

    return (
        <form
            onSubmit={submit}
            onDragOver={(event) => {
                if (!draggable) {
                    return;
                }

                event.preventDefault();
                onDragOver?.();
            }}
            onDrop={(event) => {
                if (!draggable) {
                    return;
                }

                event.preventDefault();
                onDrop?.();
            }}
            className={[
                'rounded-[5px] border bg-white px-2.5 py-2 transition',
                isDropTarget
                    ? 'border-[#203529] ring-2 ring-[#203529]/10'
                    : isDragActive
                      ? 'border-slate-300 opacity-70'
                      : 'border-slate-200',
            ].join(' ')}
        >
            <div className="flex items-center gap-2.5">
                <button
                    type="button"
                    draggable={draggable}
                    onDragStart={(event) => {
                        if (!draggable) {
                            return;
                        }

                        event.dataTransfer.effectAllowed = 'move';
                        onDragStart?.();
                    }}
                    onDragEnd={() => onDragEnd?.()}
                    className={[
                        'flex size-7 shrink-0 items-center justify-center rounded-[5px] text-slate-300 transition',
                        draggable
                            ? 'cursor-grab hover:text-slate-500'
                            : 'cursor-default opacity-40',
                    ].join(' ')}
                    aria-label="Reorder answer settings"
                >
                    <GripVertical className="size-4" />
                </button>

                <div className="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900">
                    {data.label || option.label || `Answer ${data.sort_order}`}
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="shrink-0 text-xs"
                    onClick={() => setExpanded((current) => !current)}
                >
                    {expanded ? 'Hide' : 'Details'}
                </Button>
            </div>

            <div className="mt-3 space-y-2.5 border-t border-slate-100 pt-3">
                {supportsImage ? (
                    <div className="space-y-1.5">
                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                            Image
                        </label>
                        <input
                            ref={imageInputRef}
                            type="file"
                            accept="image/*"
                            onChange={(event) =>
                                updateField(
                                    'image',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                            className="hidden"
                        />
                        <button
                            type="button"
                            onClick={() => imageInputRef.current?.click()}
                            className="flex w-full items-center gap-3 rounded-[5px] border border-slate-200 px-2.5 py-2 text-left transition hover:border-slate-300"
                        >
                            <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-[5px] bg-slate-100">
                                {imagePreviewUrl ? (
                                    <img
                                        src={imagePreviewUrl}
                                        alt={data.label || option.label}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <ImageIcon className="size-4 text-slate-400" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="text-sm font-medium text-slate-900">
                                    {imagePreviewUrl ? 'Change image' : 'Select image'}
                                </div>
                                {data.image instanceof File ? (
                                    <div className="mt-0.5 truncate text-xs text-slate-500">
                                        {data.image.name}
                                    </div>
                                ) : null}
                            </div>
                        </button>
                    </div>
                ) : null}

                <ToggleField
                    checked={data.scoring_enabled}
                    onChange={(checked) => updateField('scoring_enabled', checked)}
                    label="Score Answer"
                />
                {supportsJump ? (
                    <ToggleField
                        checked={data.jump_enabled}
                        onChange={(checked) => updateField('jump_enabled', checked)}
                        label="Jump to Question"
                    />
                ) : null}

                {expanded || data.scoring_enabled || (supportsJump && data.jump_enabled) ? (
                    <div className="space-y-3 pt-1">
                        {data.scoring_enabled ? (
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Score Value
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.score_value}
                                    onChange={(event) =>
                                        updateField('score_value', event.target.value)
                                    }
                                />
                            </div>
                        ) : null}

                        {supportsJump && data.jump_enabled ? (
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Jump to Question
                                </label>
                                <select
                                    value={data.jump_to_question_id}
                                    onChange={(event) =>
                                        updateField(
                                            'jump_to_question_id',
                                            event.target.value,
                                        )
                                    }
                                    className="flex w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-sm"
                                >
                                    <option value="">Select target</option>
                                    {questionTargets.map((target) => (
                                        <option key={target.id} value={target.id}>
                                            {target.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        ) : null}

                        {fieldError(errors, [
                            'label',
                            'score_value',
                            'jump_to_question_id',
                        ]) ? (
                            <div className="rounded-[5px] border border-rose-200 bg-rose-50 px-2.5 py-2 text-sm text-rose-900">
                                {fieldError(errors, [
                                    'label',
                                    'score_value',
                                    'jump_to_question_id',
                                ])}
                            </div>
                        ) : null}
                    </div>
                ) : null}

                <div className="flex pt-1">
                    <Button
                        type="submit"
                        size="sm"
                        disabled={processing}
                        className="w-full justify-center sm:w-auto"
                    >
                        Save Settings
                    </Button>
                </div>
            </div>
        </form>
    );
}

function useQuestionConfigPanel({
    scoreboard,
    questions,
    selectedQuestionId,
    questionTargets,
    questionTypeOptions,
}) {
    const cachedDrafts = readBuilderDraftCache(scoreboard.id);
    const [questionDrafts, setQuestionDrafts] = useState(
        () => cachedDrafts?.questionDrafts ?? {},
    );
    const orderedQuestions = questions
        .map((currentQuestion) => ({
            ...currentQuestion,
            ...(questionDrafts[currentQuestion.id] ?? {}),
        }))
        .sort(
            (left, right) =>
                getQuestionSortOrder(left, questionDrafts) -
                    getQuestionSortOrder(right, questionDrafts) || left.id - right.id,
        );
    const question =
        orderedQuestions.find((item) => item.id === selectedQuestionId) ??
        orderedQuestions[0] ??
        null;
    const questionForm = useForm(buildQuestionFormData(question));
    const [activeTab, setActiveTab] = useState('question');
    const [optionDrafts, setOptionDrafts] = useState(
        () => cachedDrafts?.optionDrafts ?? {},
    );
    const [optionProcessingMap, setOptionProcessingMap] = useState({});
    const [scoringCategoryPanelVisibility, setScoringCategoryPanelVisibility] =
        useState({});
    const [draggedQuestionId, setDraggedQuestionId] = useState(null);
    const [dropTargetQuestionId, setDropTargetQuestionId] = useState(null);
    const [draggedOptionId, setDraggedOptionId] = useState(null);
    const [dropTargetOptionId, setDropTargetOptionId] = useState(null);
    const lastSavedQuestionSnapshotsRef = useRef({});
    const lastSavedOptionSnapshotsRef = useRef({});
    const [saveState, setSaveState] = useState('saved');
    const [saveError, setSaveError] = useState(null);
    const [restoredDraftNotice, setRestoredDraftNotice] = useState(
        () => Boolean(cachedDrafts),
    );

    useEffect(() => {
        lastSavedQuestionSnapshotsRef.current = questions.reduce(
            (snapshots, currentQuestion) => ({
                ...snapshots,
                [currentQuestion.id]: serializeDraft(buildQuestionFormData(currentQuestion)),
            }),
            {},
        );
    }, [questions]);

    useEffect(() => {
        const optionSnapshots = {};

        questions.forEach((currentQuestion) => {
            (currentQuestion.options ?? []).forEach((option) => {
                optionSnapshots[option.id] = serializeDraft(buildOptionDraft(option));
            });
        });

        lastSavedOptionSnapshotsRef.current = optionSnapshots;
    }, [questions]);

    useEffect(() => {
        const nextData = question?.id
            ? questionDrafts[question.id] ?? buildQuestionFormData(question)
            : buildQuestionFormData(question);

        questionForm.setData(nextData);

        if (question?.options?.length) {
            setOptionDrafts((current) => {
                const nextDrafts = { ...current };

                question.options.forEach((option) => {
                    if (!nextDrafts[option.id]) {
                        nextDrafts[option.id] = buildOptionDraft(option);
                    }
                });

                return nextDrafts;
            });
        }

        setActiveTab('question');
        setDraggedQuestionId(null);
        setDropTargetQuestionId(null);
        setDraggedOptionId(null);
        setDropTargetOptionId(null);
    }, [question?.id]);

    useEffect(() => {
        if (
            Object.keys(questionDrafts).length === 0 &&
            Object.keys(optionDrafts).length === 0
        ) {
            clearBuilderDraftCache(scoreboard.id);

            return;
        }

        writeBuilderDraftCache(scoreboard.id, {
            questionDrafts,
            optionDrafts,
        });
    }, [optionDrafts, questionDrafts, scoreboard.id]);

    useEffect(() => {
        if (!question?.id || !questionTypeUsesAnswerTabScoringCategory(question.question_type)) {
            return;
        }

        setScoringCategoryPanelVisibility((current) => {
            if (current[question.id] !== undefined) {
                return current;
            }

            return {
                ...current,
                [question.id]:
                    Boolean(question.scoring_category)
                    && question.scoring_category !== 'overall_only',
            };
        });
    }, [question?.id, question?.question_type, question?.scoring_category]);

    useEffect(() => {
        if (!question?.id) {
            return;
        }

        const draftSortOrder = questionDrafts[question.id]?.sort_order;

        if (
            draftSortOrder !== undefined &&
            Number(questionForm.data.sort_order) !== Number(draftSortOrder)
        ) {
            questionForm.setData('sort_order', draftSortOrder);
        }
    }, [question?.id, questionDrafts, questionForm]);

    const setValue = (field, value) => {
        setSaveError(null);
        questionForm.setData(field, value);

        if (question?.id) {
            setQuestionDrafts((current) => ({
                ...current,
                [question.id]: {
                    ...(current[question.id] ?? buildQuestionFormData(question)),
                    [field]: value,
                },
            }));
        }
    };

    const setOptionValue = (optionId, field, value) => {
        setSaveError(null);
        const currentOption =
            question?.options?.find((item) => item.id === optionId) ?? null;

        setOptionDrafts((current) => ({
            ...current,
            [optionId]: {
                ...(current[optionId] ?? buildOptionDraft(currentOption)),
                [field]: value,
            },
        }));
    };

    const buildQuestionPayload = (questionId, overrides = {}) => {
        const sourceQuestion = questions.find((item) => item.id === questionId) ?? null;

        if (!sourceQuestion) {
            return null;
        }

        const baseDraft =
            question?.id === questionId
                ? questionForm.data
                : questionDrafts[questionId] ?? buildQuestionFormData(sourceQuestion);

        return {
            ...baseDraft,
            ...overrides,
            sort_order: Number(
                overrides.sort_order ??
                    baseDraft.sort_order ??
                    sourceQuestion.sort_order ??
                    1,
            ),
        };
    };

    const buildOptionPayload = (targetOption, overrides = {}) => {
        const sourceOption =
            question?.options?.find((item) => item.id === targetOption.id) ??
            targetOption;
        const draft = optionDrafts[targetOption.id] ?? buildOptionDraft(sourceOption);
        const nextDraft = {
            ...draft,
            ...overrides,
        };

        return {
            label: nextDraft.label ?? '',
            internal_value:
                nextDraft.internal_value ??
                sourceOption.internal_value ??
                '',
            sort_order: Number(nextDraft.sort_order ?? sourceOption.sort_order ?? 1),
            is_correct: Boolean(nextDraft.is_correct),
            scoring_enabled: Boolean(nextDraft.scoring_enabled),
            score_value: nextDraft.scoring_enabled
                ? nextDraft.score_value === ''
                    ? null
                    : nextDraft.score_value
                : null,
            jump_enabled: Boolean(nextDraft.jump_enabled),
            jump_to_question_id: nextDraft.jump_enabled
                ? nextDraft.jump_to_question_id || null
                : null,
            is_other_option: Boolean(
                nextDraft.is_other_option ?? sourceOption.is_other_option,
            ),
            image: nextDraft.image ?? null,
        };
    };

    const findPersistedOptionMeta = (optionId) => {
        for (const currentQuestion of questions) {
            const option = currentQuestion.options?.find((item) => item.id === optionId);

            if (option) {
                return {
                    question: currentQuestion,
                    option,
                };
            }
        }

        return null;
    };

    const saveOption = (optionId, overrides = {}) => {
        const optionMeta = findPersistedOptionMeta(optionId);

        if (!optionMeta) {
            return Promise.resolve();
        }

        const { question: sourceQuestion, option: targetOption } = optionMeta;

        if (!targetOption || targetOption.is_virtual) {
            return Promise.resolve();
        }

        const payload = buildOptionPayload(targetOption, overrides);
        const snapshot = serializeDraft({
            ...(optionDrafts[optionId] ?? buildOptionDraft(targetOption)),
            ...overrides,
        });

        setOptionProcessingMap((current) => ({
            ...current,
            [optionId]: true,
        }));

        return new Promise((resolve, reject) => {
            router.patch(
                route('admin.scoreboards.options.update', {
                    assessment: scoreboard.id,
                    question: sourceQuestion.id,
                    option: optionId,
                }),
                payload,
                {
                    preserveScroll: true,
                    forceFormData: true,
                    onSuccess: () => {
                        lastSavedOptionSnapshotsRef.current = {
                            ...lastSavedOptionSnapshotsRef.current,
                            [optionId]: snapshot,
                        };
                        setSaveError(null);
                        resolve(true);
                    },
                    onError: (errors) => reject(errors),
                    onFinish: () => {
                        setOptionProcessingMap((current) => ({
                            ...current,
                            [optionId]: false,
                        }));
                    },
                },
            );
        });
    };

    const toggleCorrectOption = (targetOption) => {
        if (!question || !targetOption || targetOption.is_virtual) {
            return;
        }

        const currentQuestionType = questionForm.data.question_type;
        const currentAllowMultiSelect = questionForm.data.allow_multi_select;
        const singleCorrect = questionTypeSupportsSingleCorrectSelection(
            currentQuestionType,
            currentAllowMultiSelect,
        );

        setOptionDrafts((current) => {
            const next = { ...current };
            const targetDraft =
                next[targetOption.id] ?? buildOptionDraft(targetOption);
            const nextEnabled = !Boolean(targetDraft.is_correct);

            if (singleCorrect && nextEnabled) {
                (question.options ?? []).forEach((option) => {
                    if (option.id === targetOption.id) {
                        return;
                    }

                    const optionDraft = next[option.id] ?? buildOptionDraft(option);
                    next[option.id] = {
                        ...optionDraft,
                        is_correct: false,
                    };
                });
            }

            next[targetOption.id] = {
                ...targetDraft,
                is_correct: nextEnabled,
            };

            return next;
        });
    };

    const saveQuestion = (questionId = question?.id) => {
        if (!questionId) {
            return Promise.resolve();
        }

        const payload = buildQuestionPayload(questionId);

        if (!payload) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            router.patch(
                route('admin.scoreboards.questions.update', {
                    assessment: scoreboard.id,
                    question: questionId,
                }),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        lastSavedQuestionSnapshotsRef.current = {
                            ...lastSavedQuestionSnapshotsRef.current,
                            [questionId]: serializeDraft(payload),
                        };
                        setSaveError(null);
                        resolve(true);
                    },
                    onError: (errors) => reject(errors),
                },
            );
        });
    };

    const submitQuestion = (event) => {
        event.preventDefault();
        saveQuestion(question?.id);
    };

    const sharedPreviewValues = questionForm.data;
    const primaryError = Object.values(questionForm.errors)[0];
    const orderedQuestionTargets = orderedQuestions.map((currentQuestion) => ({
        id: currentQuestion.id,
        label: `${
            currentQuestion.title?.trim() || `Question ${currentQuestion.sort_order}`
        } (#${currentQuestion.sort_order})`.trim(),
    }));
    const visibleOptions = (() => {
        if (!question) {
            return [];
        }

        let options = question.options || [];

        if (question.question_type !== 'multiple_choice_buttons') {
            options = options.filter((option) => !option.is_other_option);
        }

        if (
            question.question_type === 'multiple_choice_buttons' &&
            !questionForm.data.allow_other_option
        ) {
            options = options.filter((option) => !option.is_other_option);
        }

        if (
            question.question_type === 'yes_no_maybe'
        ) {
            options = options.filter((option) => ['yes', 'no'].includes(option.internal_value));

            if (options.length === 0) {
                options = buildVirtualYesNoOptions();
            }
        }

        if (
            question.question_type === 'multiple_choice_buttons' &&
            questionForm.data.allow_other_option &&
            !options.some((option) => option.is_other_option)
        ) {
            options = [
                ...options,
                {
                    id: 'virtual-other-option',
                    label: 'Other',
                    internal_value: 'other',
                    sort_order: options.length + 1,
                    is_correct: false,
                    scoring_enabled: false,
                    score_value: '',
                    jump_enabled: false,
                    jump_to_question_id: '',
                    is_other_option: true,
                    is_fixed_option: true,
                    is_virtual: true,
                    image_url: null,
                },
            ];
        }

        const persistedOptions = options
            .filter((option) => !option.is_virtual)
            .sort(
                (left, right) =>
                    getOptionSortOrder(left, optionDrafts) -
                        getOptionSortOrder(right, optionDrafts) || left.id - right.id,
            );
        const virtualOptions = options.filter((option) => option.is_virtual);

        return [...persistedOptions, ...virtualOptions];
    })();
    const supportsInstruction = question
        ? question.question_type !== 'info_screen'
        : false;
    const supportsRequired = question
        ? question.question_type !== 'info_screen'
        : false;
    const supportsRandomize = question
        ? [
              'yes_no_maybe',
              'multiple_choice_buttons',
              'multiple_choice_checkboxes',
              'radio_buttons',
              'image_button',
          ].includes(question.question_type)
        : false;
    const supportsAnswerTabScoringCategory = question
        ? questionTypeUsesAnswerTabScoringCategory(question.question_type)
        : false;
    const scoringCategoryPanelEnabled = question?.id
        ? scoringCategoryPanelVisibility[question.id]
            ?? (Boolean(questionForm.data.scoring_category)
                && questionForm.data.scoring_category !== 'overall_only')
        : false;

    const hasUnsavedQuestionChanges = questions.some((currentQuestion) => {
        const currentDraft = buildQuestionPayload(currentQuestion.id);

        if (!currentDraft) {
            return false;
        }

        return (
            serializeDraft(currentDraft) !==
            lastSavedQuestionSnapshotsRef.current[currentQuestion.id]
        );
    });
    const persistedOptions = questions
        .flatMap((currentQuestion) => currentQuestion.options ?? [])
        .filter((option) => !option.is_virtual);
    const hasUnsavedOptionChanges = persistedOptions.some((option) => {
        const currentDraft = optionDrafts[option.id] ?? buildOptionDraft(option);
        return (
            serializeDraft(currentDraft) !==
            lastSavedOptionSnapshotsRef.current[option.id]
        );
    });
    const hasUnsavedChanges = hasUnsavedQuestionChanges || hasUnsavedOptionChanges;

    useEffect(() => {
        if (saveState === 'saving') {
            return;
        }

        setSaveState(hasUnsavedChanges ? 'unsaved' : 'saved');
    }, [hasUnsavedChanges, saveState]);

    const saveAllChanges = async () => {
        if (!hasUnsavedChanges || saveState === 'saving') {
            return;
        }

        setSaveState('saving');
        setSaveError(null);

        try {
            const changedQuestionIds = questions
                .filter((currentQuestion) => {
                    const currentDraft = buildQuestionPayload(currentQuestion.id);

                    if (!currentDraft) {
                        return false;
                    }

                    return (
                        serializeDraft(currentDraft) !==
                        lastSavedQuestionSnapshotsRef.current[currentQuestion.id]
                    );
                })
                .map((currentQuestion) => currentQuestion.id);

            for (const questionId of changedQuestionIds) {
                await saveQuestion(questionId);
            }

            const changedOptions = persistedOptions.filter((option) => {
                const currentDraft = optionDrafts[option.id] ?? buildOptionDraft(option);
                return (
                    serializeDraft(currentDraft) !==
                    lastSavedOptionSnapshotsRef.current[option.id]
                );
            });

            for (const option of changedOptions) {
                await saveOption(option.id);
            }

            setSaveState('saved');
            setRestoredDraftNotice(false);
            if (changedQuestionIds.length > 0) {
                setQuestionDrafts((current) =>
                    omitKeys(current, changedQuestionIds),
                );
            }
            if (changedOptions.length > 0) {
                setOptionDrafts((current) =>
                    omitKeys(
                        current,
                        changedOptions.map((option) => option.id),
                    ),
                );
            }
        } catch (error) {
            setSaveState('unsaved');
            const firstMessage =
                error && typeof error === 'object'
                    ? Object.values(error)[0]
                    : null;

            setSaveError(
                typeof firstMessage === 'string'
                    ? firstMessage
                    : 'The builder could not save yet. Fix the highlighted validation issue, then try again.',
            );
        }
    };

    const reorderQuestionDrafts = (sourceQuestionId, targetQuestionId) => {
        if (
            !sourceQuestionId ||
            !targetQuestionId ||
            sourceQuestionId === targetQuestionId
        ) {
            return;
        }

        const orderedIds = orderedQuestions.map((currentQuestion) => currentQuestion.id);
        const sourceIndex = orderedIds.indexOf(sourceQuestionId);
        const targetIndex = orderedIds.indexOf(targetQuestionId);

        if (sourceIndex === -1 || targetIndex === -1) {
            return;
        }

        const nextIds = [...orderedIds];
        const [movedId] = nextIds.splice(sourceIndex, 1);
        nextIds.splice(targetIndex, 0, movedId);

        setQuestionDrafts((current) => {
            const nextDrafts = { ...current };

            nextIds.forEach((questionId, index) => {
                const sourceQuestion =
                    questions.find((item) => item.id === questionId) ??
                    orderedQuestions.find((item) => item.id === questionId);

                nextDrafts[questionId] = {
                    ...(current[questionId] ?? buildQuestionFormData(sourceQuestion)),
                    sort_order: index + 1,
                };
            });

            return nextDrafts;
        });
    };

    const startQuestionDrag = (questionId) => {
        setDraggedQuestionId(questionId);
        setDropTargetQuestionId(questionId);
    };

    const markQuestionDropTarget = (questionId) => {
        if (!draggedQuestionId || draggedQuestionId === questionId) {
            return;
        }

        setDropTargetQuestionId(questionId);
    };

    const finishQuestionDrag = () => {
        setDraggedQuestionId(null);
        setDropTargetQuestionId(null);
    };

    const dropQuestionAt = (targetQuestionId) => {
        if (!draggedQuestionId || !targetQuestionId) {
            finishQuestionDrag();
            return;
        }

        reorderQuestionDrafts(draggedQuestionId, targetQuestionId);
        finishQuestionDrag();
    };

    const reorderOptionDrafts = (sourceOptionId, targetOptionId) => {
        if (
            !question ||
            !sourceOptionId ||
            !targetOptionId ||
            sourceOptionId === targetOptionId
        ) {
            return;
        }

        const reorderableOptions = visibleOptions.filter((option) => !option.is_virtual);
        const orderedIds = reorderableOptions.map((option) => option.id);
        const sourceIndex = orderedIds.indexOf(sourceOptionId);
        const targetIndex = orderedIds.indexOf(targetOptionId);

        if (sourceIndex === -1 || targetIndex === -1) {
            return;
        }

        const nextIds = [...orderedIds];
        const [movedId] = nextIds.splice(sourceIndex, 1);
        nextIds.splice(targetIndex, 0, movedId);

        setOptionDrafts((current) => {
            const nextDrafts = { ...current };

            nextIds.forEach((optionId, index) => {
                const sourceOption =
                    question.options?.find((item) => item.id === optionId) ??
                    reorderableOptions.find((item) => item.id === optionId);

                nextDrafts[optionId] = {
                    ...(current[optionId] ?? buildOptionDraft(sourceOption)),
                    sort_order: index + 1,
                };
            });

            return nextDrafts;
        });
    };

    const startOptionDrag = (optionId) => {
        setDraggedOptionId(optionId);
        setDropTargetOptionId(optionId);
    };

    const markOptionDropTarget = (optionId) => {
        if (!draggedOptionId || draggedOptionId === optionId) {
            return;
        }

        setDropTargetOptionId(optionId);
    };

    const finishOptionDrag = () => {
        setDraggedOptionId(null);
        setDropTargetOptionId(null);
    };

    const dropOptionAt = (targetOptionId) => {
        if (!draggedOptionId || !targetOptionId) {
            finishOptionDrag();
            return;
        }

        reorderOptionDrafts(draggedOptionId, targetOptionId);
        finishOptionDrag();
    };

    const questionTabContent = question ? (
        <form onSubmit={submitQuestion} className="divide-y divide-slate-100">
            <QuestionSection
                title="General"
                description="Core structure, type, and the essential content for this screen."
            >
                <div className="space-y-1.5">
                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                        Question Type
                    </label>
                    <select
                        value={questionForm.data.question_type}
                        onChange={(event) =>
                            setValue('question_type', event.target.value)
                        }
                        className="flex w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-sm"
                    >
                        {questionTypeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                        Sort Order
                    </label>
                    <Input
                        type="number"
                        min="1"
                        value={questionForm.data.sort_order}
                        onChange={(event) =>
                            setValue('sort_order', event.target.value)
                        }
                    />
                </div>
            </QuestionSection>

            <QuestionSection
                title="Behaviour"
                description="Screen-level rules that affect answering and navigation."
            >
                {supportsInstruction ? (
                    <ToggleField
                        checked={questionForm.data.show_instruction}
                        onChange={(checked) =>
                            setValue('show_instruction', checked)
                        }
                        label="Show Instruction"
                    />
                ) : null}

                {supportsInstruction && questionForm.data.show_instruction ? (
                    <Textarea
                        value={questionForm.data.instruction_text}
                        onChange={(event) =>
                            setValue('instruction_text', event.target.value)
                        }
                        className="min-h-24"
                        placeholder="Instruction text appears on the canvas preview."
                    />
                ) : null}

                {supportsRequired ? (
                    <ToggleField
                        checked={questionForm.data.required}
                        onChange={(checked) => setValue('required', checked)}
                        label="Required"
                    />
                ) : null}

                {supportsRandomize ? (
                    <ToggleField
                        checked={questionForm.data.randomize_answers_order}
                        onChange={(checked) =>
                            setValue('randomize_answers_order', checked)
                        }
                        label="Randomize Answers Order"
                    />
                ) : null}
            </QuestionSection>

            <QuestionSection
                title="Jump"
                description="Question-level jump applies after the screen is completed."
            >
                <ToggleField
                    checked={questionForm.data.jump_enabled}
                    onChange={(checked) => setValue('jump_enabled', checked)}
                    label="Jump to Question"
                />

                {questionForm.data.jump_enabled ? (
                    <div className="space-y-1.5">
                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                            Jump to Question
                        </label>
                        <select
                            value={questionForm.data.jump_to_question_id}
                            onChange={(event) =>
                                setValue('jump_to_question_id', event.target.value)
                            }
                            className="flex w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-sm"
                        >
                            <option value="">Select target</option>
                            {orderedQuestionTargets.map((target) => (
                                <option key={target.id} value={target.id}>
                                    {target.label}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : null}
            </QuestionSection>

            <QuestionSection
                title="Type-specific Settings"
                description="Only the settings that matter for the active question type appear here."
            >
                {questionForm.data.question_type === 'yes_no_maybe' ? (
                    <div className="rounded-[5px] bg-slate-50 px-2.5 py-2 text-sm text-slate-600">
                        This question type is treated as <span className="font-semibold text-slate-900">Yes / No only</span>. Maybe is hidden from both builder and student flow.
                    </div>
                ) : null}

                {optionBasedTypes.includes(questionForm.data.question_type) &&
                questionForm.data.question_type !== 'yes_no_maybe' ? (
                    <div className="space-y-3">
                        {questionForm.data.question_type ===
                            'multiple_choice_buttons' ||
                        questionForm.data.question_type === 'image_button' ? (
                            <ToggleField
                                checked={questionForm.data.allow_multi_select}
                                onChange={(checked) =>
                                    setValue('allow_multi_select', checked)
                                }
                                label="Allow Multi-select"
                            />
                        ) : null}

                        {questionForm.data.question_type ===
                        'multiple_choice_buttons' ? (
                            <ToggleField
                                checked={questionForm.data.allow_other_option}
                                onChange={(checked) =>
                                    setValue('allow_other_option', checked)
                                }
                                label='"Other" Option'
                            />
                        ) : null}

                        {questionForm.data.question_type === 'image_button' ? (
                            <>
                                <ToggleField
                                    checked={questionForm.data.show_labels}
                                    onChange={(checked) =>
                                        setValue('show_labels', checked)
                                    }
                                    label="Show Labels"
                                />
                                <div className="grid gap-3 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                            Answer Image Fit
                                        </label>
                                        <select
                                            value={questionForm.data.answer_image_fit}
                                            onChange={(event) =>
                                                setValue(
                                                    'answer_image_fit',
                                                    event.target.value,
                                                )
                                            }
                                            className="flex w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-sm"
                                        >
                                            <option value="cover">Cover</option>
                                            <option value="contain">Contain</option>
                                        </select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                            Answers Per Row
                                        </label>
                                        <select
                                            value={questionForm.data.answers_per_row}
                                            onChange={(event) =>
                                                setValue(
                                                    'answers_per_row',
                                                    event.target.value,
                                                )
                                            }
                                            className="flex w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-sm"
                                        >
                                            <option value="2">2</option>
                                            <option value="4">4</option>
                                        </select>
                                    </div>
                                </div>
                            </>
                        ) : null}

                        {questionForm.data.question_type ===
                        'multiple_choice_checkboxes' ? (
                            <div className="space-y-3">
                                <div className="grid gap-3 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                            Min Count
                                        </label>
                                        <Input
                                            type="number"
                                            min="1"
                                            value={questionForm.data.min_count}
                                            onChange={(event) =>
                                                setValue('min_count', event.target.value)
                                            }
                                        />
                                        {fieldError(questionForm.errors, ['min_count']) ? (
                                            <div className="text-xs text-rose-600">
                                                {fieldError(questionForm.errors, ['min_count'])}
                                            </div>
                                        ) : null}
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                            Max Count
                                        </label>
                                        <Input
                                            type="number"
                                            min="1"
                                            value={questionForm.data.max_count}
                                            onChange={(event) =>
                                                setValue('max_count', event.target.value)
                                            }
                                        />
                                        {fieldError(questionForm.errors, ['max_count']) ? (
                                            <div className="text-xs text-rose-600">
                                                {fieldError(questionForm.errors, ['max_count'])}
                                            </div>
                                        ) : null}
                                    </div>
                                </div>
                                <div className="rounded-[5px] bg-slate-50 px-2.5 py-2 text-xs leading-6 text-slate-600">
                                    Students must select at least the minimum count and cannot exceed the maximum count.
                                </div>
                            </div>
                        ) : null}
                    </div>
                ) : null}

                {scaleTypes.includes(questionForm.data.question_type) ? (
                    <div className="space-y-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Score Range From
                                </label>
                                <Input
                                    type="number"
                                    step={
                                        questionForm.data.question_type === 'sliding_scale'
                                            ? '0.01'
                                            : '1'
                                    }
                                    value={questionForm.data.score_range_min}
                                    onChange={(event) =>
                                        setValue('score_range_min', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Score Range To
                                </label>
                                <Input
                                    type="number"
                                    step={
                                        questionForm.data.question_type === 'sliding_scale'
                                            ? '0.01'
                                            : '1'
                                    }
                                    value={questionForm.data.score_range_max}
                                    onChange={(event) =>
                                        setValue('score_range_max', event.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            {questionForm.data.question_type === 'sliding_scale' ? (
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Starting Score
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={questionForm.data.starting_score}
                                        onChange={(event) =>
                                            setValue('starting_score', event.target.value)
                                        }
                                    />
                                </div>
                            ) : null}
                            {questionForm.data.question_type === 'divided_scale' ? (
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Section Count
                                    </label>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={questionForm.data.section_count}
                                        onChange={(event) =>
                                            setValue('section_count', event.target.value)
                                        }
                                    />
                                </div>
                            ) : null}
                        </div>

                        {(questionForm.data.question_type === 'sliding_scale' ||
                            questionForm.data.question_type === 'linear_scale' ||
                            questionForm.data.question_type === 'divided_scale') ? (
                            <div className="grid gap-3 md:grid-cols-3">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Left
                                    </label>
                                    <Input
                                        value={questionForm.data.left_label}
                                        onChange={(event) =>
                                            setValue('left_label', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Center
                                    </label>
                                    <Input
                                        value={questionForm.data.center_label}
                                        onChange={(event) =>
                                            setValue('center_label', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Right
                                    </label>
                                    <Input
                                        value={questionForm.data.right_label}
                                        onChange={(event) =>
                                            setValue('right_label', event.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        ) : null}

                        {questionForm.data.question_type === 'sliding_scale' ? (
                            <>
                                <ToggleField
                                    checked={questionForm.data.show_score_tooltip}
                                    onChange={(checked) =>
                                        setValue('show_score_tooltip', checked)
                                    }
                                    label="Show Score Tooltip"
                                />

                                {questionForm.data.show_score_tooltip ? (
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                            Score Tooltip Format
                                        </label>
                                        <Input
                                            value={questionForm.data.score_tooltip_format}
                                            onChange={(event) =>
                                                setValue('score_tooltip_format', event.target.value)
                                            }
                                        />
                                    </div>
                                ) : null}
                            </>
                        ) : null}
                    </div>
                ) : null}

                {questionForm.data.question_type === 'numeric' ? (
                    <div className="space-y-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Range From
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={questionForm.data.score_range_min}
                                    onChange={(event) =>
                                        setValue('score_range_min', event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Range To
                                </label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={questionForm.data.score_range_max}
                                    onChange={(event) =>
                                        setValue('score_range_max', event.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <ToggleField
                            checked={questionForm.data.allow_decimals}
                            onChange={(checked) =>
                                setValue('allow_decimals', checked)
                            }
                            label="Allow Decimals"
                        />
                    </div>
                ) : null}

                {questionForm.data.question_type === 'open_text' ? (
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                Input Type
                            </label>
                            <select
                                value={questionForm.data.input_type}
                                onChange={(event) =>
                                    setValue('input_type', event.target.value)
                                }
                                className="flex w-full rounded-[5px] border border-slate-200 bg-white px-2.5 py-2 text-sm"
                            >
                                <option value="single_line">Single line</option>
                                <option value="multi_line">Multi line</option>
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                Character Limit
                            </label>
                            <Input
                                type="number"
                                min="1"
                                value={questionForm.data.character_limit}
                                onChange={(event) =>
                                    setValue('character_limit', event.target.value)
                                }
                            />
                        </div>
                    </div>
                ) : null}
            </QuestionSection>

            {primaryError ? (
                <div className="py-2">
                    <div className="rounded-[5px] border border-rose-200 bg-rose-50 px-2.5 py-2 text-sm text-rose-900">
                        {primaryError}
                    </div>
                </div>
            ) : null}

            <div className="flex flex-wrap gap-3 pt-4">
                <Button
                    type="button"
                    variant="destructive"
                    onClick={() =>
                        router.delete(
                            route('admin.scoreboards.questions.destroy', {
                                assessment: scoreboard.id,
                                question: question.id,
                            }),
                            { preserveScroll: true },
                        )
                    }
                >
                    Delete Question
                </Button>
            </div>
        </form>
    ) : (
        <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-600">
            Select a question to edit its configuration.
        </div>
    );

    const answersTabContent = question ? (
        optionBasedTypes.includes(question.question_type) ? (
            <div className="space-y-2.5">
                {visibleOptions.map((option) =>
                    option.is_virtual ? (
                        <div
                            key={option.id}
                            className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-600"
                        >
                            <div className="font-medium text-slate-900">{option.label}</div>
                            <div className="mt-1 leading-6">
                                {question.question_type === 'yes_no_maybe'
                                    ? 'Save the question once to persist the fixed Yes / No answers automatically.'
                                    : 'Save the question first to create the special answer row, then continue configuring it here.'}
                            </div>
                        </div>
                    ) : (
                        <AnswerRow
                            key={option.id}
                            scoreboardId={scoreboard.id}
                            question={question}
                            option={option}
                            questionTargets={orderedQuestionTargets}
                            draft={optionDrafts[option.id] ?? buildOptionDraft(option)}
                            setOptionValue={setOptionValue}
                            draggable={!option.is_virtual}
                            isDragActive={draggedOptionId === option.id}
                            isDropTarget={dropTargetOptionId === option.id}
                            onDragStart={() => startOptionDrag(option.id)}
                            onDragOver={() => markOptionDropTarget(option.id)}
                            onDrop={() => dropOptionAt(option.id)}
                            onDragEnd={finishOptionDrag}
                        />
                    ),
                )}
            </div>
        ) : (
            supportsAnswerTabScoringCategory ? (
                <div className="space-y-3">
                    <ToggleField
                        checked={scoringCategoryPanelEnabled}
                        onChange={(checked) => {
                            setScoringCategoryPanelVisibility((current) => ({
                                ...current,
                                [question.id]: checked,
                            }));

                            if (!checked) {
                                setValue('scoring_category', 'overall_only');
                            }
                        }}
                        label="Scoring Category"
                        description="Turn on to reveal the scoring category input for this question type."
                    />

                    {scoringCategoryPanelEnabled ? (
                        <div className="space-y-1.5">
                            <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                Scoring Category
                            </label>
                            <Input
                                value={questionForm.data.scoring_category}
                                onChange={(event) =>
                                    setValue('scoring_category', event.target.value)
                                }
                                placeholder="Type a scoring category"
                            />
                        </div>
                    ) : null}
                </div>
            ) : (
                <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-600">
                    This question type does not need answer-specific settings.
                </div>
            )
        )
    ) : (
        <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2 text-sm text-slate-600">
            Select a question first to manage its answers.
        </div>
    );

    const PanelContent = (
        <div className="space-y-4">
            <div className="grid h-auto w-full grid-cols-2 rounded-[5px] bg-slate-100 p-1">
                <button
                    type="button"
                    onClick={() => setActiveTab('question')}
                    className={[
                        'rounded-[5px] px-2.5 py-2 text-sm font-medium transition',
                        activeTab === 'question'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700',
                    ].join(' ')}
                    aria-pressed={activeTab === 'question'}
                >
                    Question
                </button>
                <button
                    type="button"
                    onClick={() => setActiveTab('answers')}
                    className={[
                        'rounded-[5px] px-2.5 py-2 text-sm font-medium transition',
                        activeTab === 'answers'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700',
                    ].join(' ')}
                    aria-pressed={activeTab === 'answers'}
                >
                    Answers
                </button>
            </div>

            <div>{activeTab === 'question' ? questionTabContent : answersTabContent}</div>
        </div>
    );

    const canvasError = primaryError;

    return {
        orderedQuestions,
        selectedQuestion: question,
        panel: (
            <section className="flex min-h-0 flex-col rounded-[5px] border border-slate-200 bg-white xl:sticky xl:top-6 xl:h-[calc(100vh-12rem)]">
                <header className="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-2.5 py-2">
                    <div className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                        <Settings2 className="size-3.5" />
                        Configuration
                    </div>
                    {question ? (
                        <span className="rounded-[5px] bg-slate-100 px-2.5 py-2 text-xs font-medium text-slate-500">
                            {formatQuestionType(question.question_type)}
                        </span>
                    ) : null}
                </header>

                <div className="min-h-0 flex-1 overflow-y-auto p-4 [scrollbar-gutter:stable]">
                    {PanelContent}
                </div>
            </section>
        ),
        mobilePanel: PanelContent,
        values: sharedPreviewValues,
        visibleOptions,
        setValue,
        optionDrafts,
        setOptionValue,
        toggleCorrectOption,
        draggedQuestionId,
        dropTargetQuestionId,
        startQuestionDrag,
        markQuestionDropTarget,
        dropQuestionAt,
        finishQuestionDrag,
        draggedOptionId,
        dropTargetOptionId,
        startOptionDrag,
        markOptionDropTarget,
        dropOptionAt,
        finishOptionDrag,
        saveAllChanges,
        saveState,
        hasUnsavedChanges,
        error: canvasError,
        saveError,
        restoredDraftNotice,
    };
}

function DesignSettingsDrawer({ scoreboardId, design }) {
    const designForm = useForm({
        logo: null,
        logo_max_width: design.logo_max_width ?? '',
        logo_alignment: design.logo_alignment ?? '',
        logo_link: design.logo_link ?? '',
        header_position: design.header_position ?? '',
        section_background: design.section_background ?? '',
        top_margin: design.top_margin ?? '',
        bottom_margin: design.bottom_margin ?? '',
        footer_content: design.footer_content ?? '',
    });

    useEffect(() => {
        designForm.setData({
            logo: null,
            logo_max_width: design.logo_max_width ?? '',
            logo_alignment: design.logo_alignment ?? '',
            logo_link: design.logo_link ?? '',
            header_position: design.header_position ?? '',
            section_background: design.section_background ?? '',
            top_margin: design.top_margin ?? '',
            bottom_margin: design.bottom_margin ?? '',
            footer_content: design.footer_content ?? '',
        });
    }, [design.logo_url, design.logo_max_width, design.footer_content]);

    const submitDesign = (event) => {
        event.preventDefault();
        designForm.patch(route('admin.scoreboards.design.update', scoreboardId), {
            preserveScroll: true,
            forceFormData: true,
        });
    };

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="ghost" size="sm" className="rounded-[5px]">
                    <Palette className="size-4" />
                    <span className="hidden sm:inline">Design</span>
                </Button>
            </SheetTrigger>
            <SheetContent className="flex h-full w-full flex-col gap-0 overflow-hidden border-l border-slate-200 bg-white p-0 shadow-none sm:max-w-2xl">
                <SheetHeader className="shrink-0 border-b border-slate-200 bg-white px-2.5 py-2 text-left">
                    <SheetTitle>Design Settings</SheetTitle>
                    <SheetDescription>
                        Global visual settings that apply to the scoreboard question flow.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto bg-white px-2.5 py-2">
                    <form onSubmit={submitDesign} className="divide-y divide-slate-100">
                        <QuestionSection
                            title="Layout"
                            description="Global layout controls for the live builder canvas."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Header Position
                                    </label>
                                    <Input
                                        value={designForm.data.header_position}
                                        onChange={(event) =>
                                            designForm.setData(
                                                'header_position',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="top, inline"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Section Background
                                    </label>
                                    <Input
                                        value={designForm.data.section_background}
                                        onChange={(event) =>
                                            designForm.setData(
                                                'section_background',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="#d7d7d7"
                                    />
                                </div>
                            </div>
                        </QuestionSection>

                        <QuestionSection
                            title="Spacing & Footer"
                            description="Control the frame around the screen without overloading the main builder panel."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Top Margin
                                    </label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={designForm.data.top_margin}
                                        onChange={(event) =>
                                            designForm.setData(
                                                'top_margin',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                        Bottom Margin
                                    </label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={designForm.data.bottom_margin}
                                        onChange={(event) =>
                                            designForm.setData(
                                                'bottom_margin',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                    Footer Content
                                </label>
                                <Textarea
                                    value={designForm.data.footer_content}
                                    onChange={(event) =>
                                        designForm.setData(
                                            'footer_content',
                                            event.target.value,
                                        )
                                    }
                                    className="min-h-32"
                                />
                            </div>
                        </QuestionSection>

                        {Object.values(designForm.errors)[0] ? (
                            <div className="py-2">
                                <div className="rounded-[5px] border border-rose-200 bg-rose-50 px-2.5 py-2 text-sm text-rose-900">
                                    {Object.values(designForm.errors)[0]}
                                </div>
                            </div>
                        ) : null}

                        <div className="flex justify-end pt-5">
                            <Button type="submit" disabled={designForm.processing}>
                                Save Design Settings
                            </Button>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ResultRangeRow({ scoreboardId, range }) {
    const rangeForm = useForm({
        title: range.title ?? '',
        description: range.description ?? '',
        min_score: range.min_score ?? '',
        max_score: range.max_score ?? '',
        sort_order: range.sort_order ?? 1,
    });

    useEffect(() => {
        rangeForm.setData({
            title: range.title ?? '',
            description: range.description ?? '',
            min_score: range.min_score ?? '',
            max_score: range.max_score ?? '',
            sort_order: range.sort_order ?? 1,
        });
    }, [range.id]);

    const submit = (event) => {
        event.preventDefault();
        rangeForm.patch(
            route('admin.scoreboards.result-ranges.update', {
                assessment: scoreboardId,
                resultRange: range.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-[5px] border border-slate-200 bg-white px-2.5 py-2"
        >
            <div className="space-y-3">
                <div className="space-y-1.5">
                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                        Result Title
                    </label>
                    <Input
                        value={rangeForm.data.title}
                        onChange={(event) =>
                            rangeForm.setData('title', event.target.value)
                        }
                        placeholder="e.g., Beginner"
                    />
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <div className="space-y-1.5">
                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                            Min Score
                        </label>
                        <Input
                            type="number"
                            step="0.01"
                            value={rangeForm.data.min_score}
                            onChange={(event) =>
                                rangeForm.setData('min_score', event.target.value)
                            }
                            placeholder="Min"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                            Max Score
                        </label>
                        <Input
                            type="number"
                            step="0.01"
                            value={rangeForm.data.max_score}
                            onChange={(event) =>
                                rangeForm.setData('max_score', event.target.value)
                            }
                            placeholder="Max"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                            Order
                        </label>
                        <Input
                            type="number"
                            min="1"
                            value={rangeForm.data.sort_order}
                            onChange={(event) =>
                                rangeForm.setData('sort_order', event.target.value)
                            }
                            placeholder="Order"
                        />
                    </div>
                </div>

                <div className="space-y-1.5">
                    <label className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                        Description
                    </label>
                    <Textarea
                        value={rangeForm.data.description}
                        onChange={(event) =>
                            rangeForm.setData('description', event.target.value)
                        }
                        className="min-h-24"
                        placeholder="Describe what this score range means."
                    />
                </div>
            </div>

            {Object.values(rangeForm.errors)[0] ? (
                <div className="mt-3 rounded-[5px] border border-rose-200 bg-rose-50 px-2.5 py-2 text-sm text-rose-900">
                    {Object.values(rangeForm.errors)[0]}
                </div>
            ) : null}

            <div className="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3.5">
                <Button type="submit" size="sm" disabled={rangeForm.processing}>
                    Save Range
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    onClick={() =>
                        router.delete(
                            route('admin.scoreboards.result-ranges.destroy', {
                                assessment: scoreboardId,
                                resultRange: range.id,
                            }),
                            { preserveScroll: true },
                        )
                    }
                >
                    Delete
                </Button>
            </div>
        </form>
    );
}

function ResultRangesDrawer({ scoreboardId, resultRanges }) {
    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="ghost" size="sm" className="rounded-[5px]">
                    <Target className="size-4" />
                    <span className="hidden sm:inline">Ranges</span>
                </Button>
            </SheetTrigger>
            <SheetContent className="flex h-full w-full flex-col gap-0 overflow-hidden border-l border-slate-200 bg-white p-0 shadow-none sm:max-w-3xl">
                <SheetHeader className="shrink-0 border-b border-slate-200 bg-white px-2.5 py-2 text-left">
                    <SheetTitle>Result Ranges</SheetTitle>
                    <SheetDescription>
                        Configure range-based outcomes. If you leave this empty, the scoreboard can still go live and students will simply see the raw score.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto bg-white px-2.5 py-2">
                    <div className="space-y-3">
                        <div className="flex justify-end">
                            <Button
                                onClick={() =>
                                    router.post(
                                        route(
                                            'admin.scoreboards.result-ranges.store',
                                            scoreboardId,
                                        ),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Plus className="size-4" />
                                Add Result Range
                            </Button>
                        </div>

                        {resultRanges.length > 0 ? (
                            resultRanges.map((range) => (
                                <ResultRangeRow
                                    key={range.id}
                                    scoreboardId={scoreboardId}
                                    range={range}
                                />
                            ))
                        ) : (
                            <div className="rounded-[5px] border border-dashed border-slate-300 px-2.5 py-2">
                                <div className="max-w-xl">
                                    <h4 className="text-base font-semibold text-slate-900">
                                        No result ranges configured
                                    </h4>
                                    <p className="mt-2 text-sm leading-7 text-slate-600">
                                        This scoreboard can still be live. If you leave result ranges empty, the final student result will show the raw score only.
                                    </p>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

export default function ScoreboardBuilder({
    scoreboard,
    questions,
    selectedQuestionId,
    design,
    resultRanges,
    questionTypeOptions,
    questionTargets,
    status,
}) {
    const {
        orderedQuestions,
        selectedQuestion,
        panel,
        mobilePanel,
        values,
        visibleOptions,
        setValue,
        optionDrafts,
        setOptionValue,
        toggleCorrectOption,
        draggedQuestionId,
        dropTargetQuestionId,
        startQuestionDrag,
        markQuestionDropTarget,
        dropQuestionAt,
        finishQuestionDrag,
        draggedOptionId,
        dropTargetOptionId,
        startOptionDrag,
        markOptionDropTarget,
        dropOptionAt,
        finishOptionDrag,
        saveAllChanges,
        saveState,
        hasUnsavedChanges,
        error,
        saveError,
        restoredDraftNotice,
    } = useQuestionConfigPanel({
        scoreboard,
        questions,
        selectedQuestionId,
        questionTargets,
        questionTypeOptions,
    });

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">Scoreboards</Badge>
                        <Badge variant="outline">Builder</Badge>
                    </div>
                    <div className="mt-3">
                        <h2 className="text-2xl font-semibold tracking-tight text-slate-900">
                            Scoreboard Builder
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            ScoreApp-style builder shell for question screens, answers, and live preview editing.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title="Scoreboard Builder" />

            <div className="py-10">
                <div className="mx-auto max-w-[1660px] space-y-5 px-2.5 sm:px-6 lg:px-8">
                    {statusMessages[status] ? (
                        <div className="rounded-[5px] border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    ) : null}

                    {restoredDraftNotice ? (
                        <div className="rounded-[5px] border border-amber-200 bg-amber-50 px-2.5 py-2 text-sm text-amber-900">
                            Unsaved builder draft was restored in this browser. Save the builder again to persist it to the database.
                        </div>
                    ) : null}

                    {saveError ? (
                        <div className="rounded-[5px] border border-rose-200 bg-rose-50 px-2.5 py-2 text-sm text-rose-900">
                            {saveError}
                        </div>
                    ) : null}

                    <BuilderWorkspaceBar
                        scoreboard={scoreboard}
                        selectedQuestion={selectedQuestion}
                        resultRanges={resultRanges}
                        design={design}
                        mobilePanel={mobilePanel}
                        saveAllChanges={saveAllChanges}
                        saveState={saveState}
                        hasUnsavedChanges={hasUnsavedChanges}
                    />

                    <div className="hidden xl:block">
                        <div
                            className="grid min-h-0 items-start gap-5"
                            style={{
                                gridTemplateColumns:
                                    'minmax(248px,18%) minmax(680px,64%) minmax(300px,18%)',
                            }}
                        >
                            <ScoreboardNavigator
                                scoreboardId={scoreboard.id}
                                questions={orderedQuestions}
                                selectedQuestionId={selectedQuestion?.id}
                                draggedQuestionId={draggedQuestionId}
                                dropTargetQuestionId={dropTargetQuestionId}
                                startQuestionDrag={startQuestionDrag}
                                markQuestionDropTarget={markQuestionDropTarget}
                                dropQuestionAt={dropQuestionAt}
                                finishQuestionDrag={finishQuestionDrag}
                            />

                            <BuilderCanvas
                                scoreboard={scoreboard}
                                question={selectedQuestion}
                                values={values}
                                visibleOptions={visibleOptions}
                                setValue={setValue}
                                error={error}
                                design={design}
                                optionDrafts={optionDrafts}
                                setOptionValue={setOptionValue}
                                toggleCorrectOption={toggleCorrectOption}
                                draggedOptionId={draggedOptionId}
                                dropTargetOptionId={dropTargetOptionId}
                                startOptionDrag={startOptionDrag}
                                markOptionDropTarget={markOptionDropTarget}
                                dropOptionAt={dropOptionAt}
                                finishOptionDrag={finishOptionDrag}
                            />

                            <div className="min-h-0 xl:self-stretch">{panel}</div>
                        </div>
                    </div>

                    <div className="space-y-5 xl:hidden">
                        <ScoreboardNavigator
                            scoreboardId={scoreboard.id}
                            questions={orderedQuestions}
                            selectedQuestionId={selectedQuestion?.id}
                            draggedQuestionId={draggedQuestionId}
                            dropTargetQuestionId={dropTargetQuestionId}
                            startQuestionDrag={startQuestionDrag}
                            markQuestionDropTarget={markQuestionDropTarget}
                            dropQuestionAt={dropQuestionAt}
                            finishQuestionDrag={finishQuestionDrag}
                        />

                        <BuilderCanvas
                            scoreboard={scoreboard}
                            question={selectedQuestion}
                            values={values}
                            visibleOptions={visibleOptions}
                            setValue={setValue}
                            error={error}
                            design={design}
                            optionDrafts={optionDrafts}
                            setOptionValue={setOptionValue}
                            toggleCorrectOption={toggleCorrectOption}
                            draggedOptionId={draggedOptionId}
                            dropTargetOptionId={dropTargetOptionId}
                            startOptionDrag={startOptionDrag}
                            markOptionDropTarget={markOptionDropTarget}
                            dropOptionAt={dropOptionAt}
                            finishOptionDrag={finishOptionDrag}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}