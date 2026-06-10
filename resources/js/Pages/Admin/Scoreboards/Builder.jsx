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
const scoringCategoryOptions = [
    { value: 'overall_only', label: 'Overall Only' },
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
        show_maybe_answer: Boolean(question?.show_maybe_answer ?? true),
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
        input_type: question?.input_type ?? '',
        character_limit: question?.character_limit ?? '',
        show_score_tooltip: Boolean(question?.show_score_tooltip),
        score_tooltip_format: question?.score_tooltip_format ?? '',
        answer_image_fit: question?.answer_image_fit ?? '',
        answers_per_row: question?.answers_per_row ?? '',
        scoring_category: question?.scoring_category ?? 'overall_only',
        left_label: question?.left_label ?? '',
        center_label: question?.center_label ?? '',
        right_label: question?.right_label ?? '',
    };
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

function ScoreboardNavigator({ scoreboardId, questions, selectedQuestionId }) {
    return (
        <section className="flex min-h-0 flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] xl:sticky xl:top-6 xl:h-[calc(100vh-12rem)]">
            <div className="border-b border-slate-200 bg-[linear-gradient(180deg,#faf9f6_0%,#f4f2ec_100%)] px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#607362]">
                            <ListTree className="size-3.5" />
                            Structure
                        </div>
                        <h3 className="mt-2 text-base font-semibold text-slate-900">
                            Question Screens
                        </h3>
                        <p className="mt-1 text-xs text-slate-500">
                            {questions.length} screen{questions.length === 1 ? '' : 's'}
                        </p>
                    </div>

                    <Button
                        size="sm"
                        className="h-9 rounded-full px-3"
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
                </div>
            </div>

            <div className="min-h-0 space-y-2 overflow-y-auto bg-[#fcfbf8] p-3 xl:flex-1">
                {questions.length > 0 ? (
                    questions.map((question, index) => {
                        const active = question.id === selectedQuestionId;
                        const typeVisual = getQuestionTypeVisual(
                            question.question_type,
                        );
                        const TypeIcon = typeVisual.icon;

                        return (
                            <Link
                                key={question.id}
                                href={route('admin.scoreboards.builder', {
                                    assessment: scoreboardId,
                                    question: question.id,
                                })}
                                preserveScroll
                                preserveState
                                className={[
                                    'group block rounded-[22px] border px-3.5 py-3.5 transition',
                                    active
                                        ? 'border-[#203529] bg-[#203529] text-white shadow-[0_16px_26px_rgba(15,23,42,0.16)]'
                                        : 'border-slate-200 bg-white text-slate-900 hover:border-[#cfd9cf] hover:bg-[#f8f6f0]',
                                ].join(' ')}
                            >
                                <div className="flex items-start gap-3.5">
                                    <div
                                        className={[
                                            'mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-2xl text-xs font-semibold',
                                            active
                                                ? 'bg-white/12 text-white'
                                                : 'bg-white text-slate-700 shadow-sm',
                                        ].join(' ')}
                                    >
                                        {question.sort_order}
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-sm font-semibold">
                                                {getNavigatorLabel(question, index)}
                                            </span>
                                            {active && <CircleDot className="size-3.5 shrink-0" />}
                                        </div>

                                        <div className="mt-2 flex items-center gap-2 text-[11px] font-medium">
                                            <span
                                                className={[
                                                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 transition',
                                                    active
                                                        ? 'bg-white/10 text-white/82'
                                                        : 'bg-[#f5f3ed] text-slate-500',
                                                ].join(' ')}
                                            >
                                                <TypeIcon className="size-3.5" />
                                                {typeVisual.label}
                                            </span>
                                        </div>
                                    </div>

                                    <ChevronRight
                                        className={[
                                            'mt-1 size-4 shrink-0',
                                            active ? 'text-white/80' : 'text-slate-400',
                                        ].join(' ')}
                                    />
                                </div>
                            </Link>
                        );
                    })
                ) : (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
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
        'w-full rounded-2xl border border-transparent bg-transparent px-0 text-slate-900 shadow-none outline-none transition placeholder:text-slate-400 focus:border-transparent focus:ring-0',
        dominant
            ? 'min-h-[120px] text-[2rem] font-semibold leading-[1.15] tracking-tight'
            : 'text-sm font-medium',
    ].join(' ');

    return (
        <div className="space-y-2">
            <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#607362]">
                {label}
            </div>
            {multiline ? (
                <Textarea
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    className={`${baseClass} resize-none border-none bg-transparent p-0`}
                />
            ) : (
                <Input
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    className={`${baseClass} border-none bg-transparent p-0`}
                />
            )}
        </div>
    );
}

function BuilderWorkspaceBar({
    scoreboard,
    selectedQuestion,
    resultRanges,
    design,
    mobilePanel,
}) {
    return (
        <section className="overflow-hidden rounded-[34px] border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.06)]">
            <div className="border-b border-slate-200 bg-[linear-gradient(180deg,#fbfaf7_0%,#f3f1ea_100%)] px-5 py-5 lg:px-6">
                <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div className="min-w-0 space-y-4">
                        <div className="flex flex-wrap items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.22em] text-[#607362]">
                            <PenSquare className="size-3.5" />
                            Builder Workspace
                        </div>
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">{scoreboard.status}</Badge>
                                <Badge
                                    variant={
                                        scoreboard.is_active ? 'secondary' : 'outline'
                                    }
                                >
                                    {scoreboard.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                                {selectedQuestion ? (
                                    <Badge variant="outline">
                                        Screen {selectedQuestion.sort_order}
                                    </Badge>
                                ) : null}
                                <Badge variant="outline">Manual Save</Badge>
                            </div>
                            <div>
                                <h2 className="text-[1.85rem] font-semibold tracking-tight text-slate-900">
                                    {scoreboard.title}
                                </h2>
                                <p className="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                                    Three-panel builder workspace with an active screen canvas in the center and compact configuration on the right.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 xl:justify-end">
                        <ResultRangesDrawer
                            scoreboardId={scoreboard.id}
                            resultRanges={resultRanges}
                        />
                        <DesignSettingsDrawer
                            scoreboardId={scoreboard.id}
                            design={design}
                        />
                        <Button asChild variant="outline">
                            <Link href={route('admin.scoreboards.edit', scoreboard.id)}>
                                <LayoutPanelTop className="size-4" />
                                Edit Meta
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={route('admin.scoreboards.index')}>
                                <ArrowLeft className="size-4" />
                                Back to Scoreboards
                            </Link>
                        </Button>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button className="xl:hidden">
                                    <Settings2 className="size-4" />
                                    Open Panel
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
            </div>

            <div className="grid gap-3 bg-white px-5 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:px-6">
                <div className="flex items-start gap-3 rounded-[22px] border border-slate-200 bg-[#faf8f3] px-4 py-3 text-sm text-slate-600">
                    <Eye className="mt-0.5 size-4 shrink-0 text-[#607362]" />
                    <div>
                        <div className="font-medium text-slate-900">
                            {selectedQuestion
                                ? getNavigatorLabel(
                                      selectedQuestion,
                                      selectedQuestion.sort_order - 1,
                                  )
                                : 'No active screen selected'}
                        </div>
                        <div className="mt-1 leading-6 text-slate-500">
                            {selectedQuestion
                                ? 'Edit text directly in the center canvas, then save from the active config panel when you are ready to commit.'
                                : 'Create or select a question to start shaping the builder flow.'}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2 self-start rounded-[22px] border border-slate-200 bg-[#faf8f3] px-4 py-3 text-[11px] font-medium uppercase tracking-[0.16em] text-slate-400">
                    <span>{selectedQuestion ? 'Builder Ready' : 'Waiting for Screen'}</span>
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
            <div className="rounded-3xl border border-dashed border-[#cfd8ce] bg-white/70 px-5 py-6 text-sm leading-7 text-slate-600">
                This screen presents information only. Students continue through the flow without storing an answer.
            </div>
        );
    }

    if (!optionBasedTypes.includes(values.question_type)) {
        if (scaleTypes.includes(values.question_type)) {
            return (
                <div className="space-y-5 rounded-3xl border border-white/70 bg-white/85 p-5">
                    <input
                        type="range"
                        min={values.score_range_min || 0}
                        max={values.score_range_max || 10}
                        step={values.allow_decimals ? '0.01' : '1'}
                        value={values.starting_score || 0}
                        readOnly
                        className="w-full accent-[#203529]"
                    />
                    <div className="flex items-center justify-between gap-3 text-sm text-slate-500">
                        <span>{values.left_label || 'Low'}</span>
                        <span>{values.center_label || 'Balanced'}</span>
                        <span>{values.right_label || 'High'}</span>
                    </div>
                    {values.show_score_tooltip && (
                        <div className="rounded-2xl border border-slate-200 bg-[#f8f6f0] px-4 py-3 text-sm text-slate-600">
                            {values.score_tooltip_format ||
                                'Selected value will be used as raw score.'}
                        </div>
                    )}
                </div>
            );
        }

        if (values.question_type === 'numeric') {
            return (
                <div className="rounded-3xl border border-white/70 bg-white/85 p-5">
                    <div className="text-sm font-medium text-slate-700">Student Answer</div>
                    <div className="mt-3 rounded-2xl border border-slate-200 bg-[#fbfaf7] px-4 py-4 text-sm text-slate-400">
                        Numeric input field
                    </div>
                    <div className="mt-3 text-xs text-slate-500">
                        Range: {values.score_range_min || 0} to {values.score_range_max || 0}
                    </div>
                </div>
            );
        }

        return (
            <div className="rounded-3xl border border-white/70 bg-white/85 p-5">
                <div className="text-sm font-medium text-slate-700">Student Answer</div>
                <div className="mt-3 rounded-2xl border border-slate-200 bg-[#fbfaf7] px-4 py-4 text-sm text-slate-400">
                    {values.input_type === 'textarea' || Number(values.character_limit || 0) > 140
                        ? 'Long text answer field'
                        : 'Single-line text answer field'}
                </div>
                {values.character_limit ? (
                    <div className="mt-3 text-xs text-slate-500">
                        Character limit: {values.character_limit}
                    </div>
                ) : null}
            </div>
        );
    }

    const visibleOptions =
        values.question_type === 'yes_no_maybe' && !values.show_maybe_answer
            ? options.filter((option) => option.internal_value !== 'maybe')
            : options;

    return (
        <div
            className="grid gap-3"
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
                            'rounded-3xl border bg-white/90 text-slate-800 shadow-sm',
                            values.question_type === 'image_button'
                                ? 'overflow-hidden border-white/70'
                                : 'border-white/70 px-4 py-4',
                        ].join(' ')}
                    >
                        {values.question_type === 'image_button' && (
                            <>
                                <div className="flex h-36 items-center justify-center bg-[#e8eee7]">
                                    {option.image_url ? (
                                        <img
                                            src={option.image_url}
                                            alt={option.label}
                                            className="h-full w-full object-cover"
                                        />
                                    ) : (
                                        <div className="text-xs font-medium uppercase tracking-[0.14em] text-slate-500">
                                            Image
                                        </div>
                                    )}
                                </div>
                                {values.show_labels && (
                                    <div className="px-4 py-3 text-sm font-medium text-slate-900">
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
                <div className="rounded-3xl border border-dashed border-[#cfd8ce] bg-white/70 px-5 py-6 text-sm text-slate-500">
                    Add answers in the Answers tab to complete this screen preview.
                </div>
            )}
        </div>
    );
}

function CenterCorrectControl({ checked, multiple, onChange, disabled = false }) {
    return (
        <label className="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
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
    saveOption,
    toggleCorrectOption,
    allowMultipleCorrect,
    processing = false,
}) {
    const data = draft;

    const updateField = (field, value) => {
        setOptionValue(option.id, field, value);
    };

    const labelValue = data.label;

    return (
        <div className="rounded-[22px] border border-slate-200 bg-white/88 px-4 py-4 shadow-sm">
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-[#eef2ec] text-xs font-semibold text-[#35513e]">
                        {data.sort_order}
                    </div>

                    {question.question_type === 'image_button' ? (
                        <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-[#eef2ec]">
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
                            <div className="min-h-11 rounded-2xl border border-slate-200 bg-[#faf8f3] px-4 py-3 text-sm font-medium text-slate-900">
                                {labelValue}
                            </div>
                        ) : (
                            <Input
                                value={labelValue}
                                onChange={(event) =>
                                    updateField('label', event.target.value)
                                }
                                placeholder="Answer label"
                                className="min-h-11 rounded-2xl border-slate-200 bg-white"
                            />
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 xl:justify-end">
                    <CenterCorrectControl
                        checked={data.is_correct}
                        multiple={allowMultipleCorrect}
                        onChange={() => toggleCorrectOption(option)}
                    />

                    {!option.is_fixed_option ? (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
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

                    {!option.is_virtual ? (
                        <Button
                            type="button"
                            size="sm"
                            disabled={processing}
                            onClick={() => saveOption(option.id)}
                        >
                            Save
                        </Button>
                    ) : null}
                </div>
            </div>
        </div>
    );
}

function BuilderCanvas({
    scoreboard,
    question,
    values,
    visibleOptions,
    setValue,
    saveQuestion,
    questionProcessing,
    error,
    design,
    optionDrafts,
    setOptionValue,
    saveOption,
    optionProcessingMap,
    toggleCorrectOption,
}) {
    const sectionBackground =
        design.section_background ||
        'linear-gradient(180deg, rgba(241,237,229,0.95) 0%, rgba(246,249,245,0.95) 46%, rgba(239,245,239,0.95) 100%)';
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
        <section className="overflow-hidden rounded-[34px] border border-slate-200 bg-white shadow-[0_24px_70px_rgba(15,23,42,0.07)]">
            <div className="border-b border-slate-200 bg-[linear-gradient(180deg,#fcfbf8_0%,#f7f6f1_100%)] px-6 py-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-[#607362]">
                            <Eye className="size-3.5" />
                            Canvas
                        </div>
                        <h3 className="mt-2 text-lg font-semibold text-slate-900">
                            Editor Preview
                        </h3>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        {question ? (
                            <span className="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 font-medium">
                                Screen {question.sort_order}
                            </span>
                        ) : null}
                        <span className="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1.5 font-medium">
                            Student Preview
                        </span>
                    </div>
                </div>
            </div>

            {question ? (
                <div className="bg-[radial-gradient(circle_at_top,rgba(234,239,232,0.45),transparent_48%),linear-gradient(180deg,#ffffff_0%,#fbfaf6_100%)] p-5 md:p-6">
                    <div
                        className="rounded-[34px] border border-white/70 p-4 shadow-[0_28px_70px_rgba(15,23,42,0.08)] md:p-6"
                        style={{ background: sectionBackground }}
                    >
                        <div
                            className="mx-auto max-w-3xl rounded-[30px] border border-white/60 bg-white/50 p-5 backdrop-blur-sm md:p-8"
                            style={{
                                paddingTop: design.top_margin || 0,
                                paddingBottom: design.bottom_margin || 0,
                            }}
                        >
                            <div className="space-y-8">
                                <div className="flex flex-col gap-3 rounded-[22px] border border-white/60 bg-white/55 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="text-sm text-slate-500">
                                        Edit the active screen directly on canvas.
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="rounded-full"
                                            onClick={saveQuestion}
                                            disabled={questionProcessing}
                                        >
                                            Save Screen
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="rounded-full"
                                            onClick={() =>
                                                router.post(
                                                    route('admin.scoreboards.questions.store', scoreboard.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Plus className="size-4" />
                                            Add Question
                                        </Button>
                                    </div>
                                </div>

                                <InlineTextBlock
                                    label="Screen Label"
                                    value={values.title}
                                    onChange={(event) =>
                                        setValue('title', event.target.value)
                                    }
                                    placeholder="Optional internal screen title"
                                />

                                {values.show_instruction ? (
                                    <div className="rounded-3xl border border-[#d7e2d6] bg-white/70 px-5 py-4">
                                        <InlineTextBlock
                                            label="Instruction"
                                            value={values.instruction_text}
                                            onChange={(event) =>
                                                setValue(
                                                    'instruction_text',
                                                    event.target.value,
                                                )
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

                                <PreviewOptionGrid
                                    question={question}
                                    values={values}
                                    optionDrafts={optionDrafts}
                                />

                                {supportsCenterAnswers ? (
                                    <div className="space-y-3 rounded-[26px] border border-white/60 bg-white/60 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <div className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#607362]">
                                                    Answers
                                                </div>
                                                <div className="mt-1 text-sm text-slate-500">
                                                    {supportsMultipleCorrectAnswers
                                                        ? 'Edit labels and choose one or more correct answers directly from the center panel.'
                                                        : 'Edit labels and choose the correct answer directly from the center panel.'}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="grid gap-3">
                                            {visibleOptions.map((option) =>
                                                option.is_virtual ? (
                                                    <div
                                                        key={option.id}
                                                        className="rounded-[22px] border border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-600"
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
                                                        saveOption={saveOption}
                                                        toggleCorrectOption={toggleCorrectOption}
                                                        allowMultipleCorrect={
                                                            supportsMultipleCorrectAnswers
                                                        }
                                                        processing={
                                                            Boolean(optionProcessingMap[option.id])
                                                        }
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
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'admin.scoreboards.options.store',
                                                                {
                                                                    assessment: scoreboard.id,
                                                                    question: question.id,
                                                                },
                                                            ),
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

                                <div className="flex items-center justify-end border-t border-slate-200 pt-6">
                                    <button
                                        type="button"
                                        className="inline-flex h-11 items-center justify-center rounded-full bg-[#203529] px-6 text-sm font-semibold text-white shadow-[0_14px_30px_rgba(32,53,41,0.24)]"
                                    >
                                        Next
                                    </button>
                                </div>

                                {design.footer_content ? (
                                    <div className="border-t border-slate-200 pt-6 text-sm leading-7 text-slate-500">
                                        {design.footer_content}
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    </div>

                    {error ? (
                        <div className="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {error}
                        </div>
                    ) : null}
                </div>
            ) : (
                <div className="p-6">
                    <div className="rounded-[30px] border border-dashed border-slate-300 bg-[#fbfaf6] px-6 py-20 text-center">
                        <div className="mx-auto max-w-md">
                            <div className="inline-flex rounded-full bg-white p-3 text-[#607362] shadow-sm">
                                <Sparkles className="size-5" />
                            </div>
                            <h3 className="mt-5 text-xl font-semibold text-slate-900">
                                Start building the first screen
                            </h3>
                            <p className="mt-2 text-sm leading-7 text-slate-500">
                                Add a question from the left panel and the canvas will turn into your live editing surface.
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
}

function QuestionSection({ title, description, children }) {
    return (
        <section className="space-y-4 rounded-[20px] border border-slate-200 bg-[#faf8f3] p-4">
            <div>
                <h4 className="text-[13px] font-semibold uppercase tracking-[0.12em] text-slate-900">
                    {title}
                </h4>
                {description ? (
                    <p className="mt-1 text-xs leading-5 text-slate-500">{description}</p>
                ) : null}
            </div>
            {children}
        </section>
    );
}

function ToggleField({ checked, onChange, label, description }) {
    return (
        <label className="flex items-center justify-between gap-4 rounded-[16px] border border-slate-200 bg-white px-4 py-3">
            <div className="min-w-0">
                <div className="text-sm font-medium text-slate-900">{label}</div>
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
            <div className="relative h-6 w-11 shrink-0 rounded-full bg-slate-200 transition peer-checked:bg-[#203529]">
                <span className="absolute left-1 top-1 size-4 rounded-full bg-white shadow-sm transition peer-checked:left-6" />
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
}) {
    const [expanded, setExpanded] = useState(false);
    const { data, setData, patch, processing, errors } = useForm(draft);

    useEffect(() => {
        setData(draft);
    }, [draft, option.id]);

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

    return (
        <form
            onSubmit={submit}
            className="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-sm font-semibold text-slate-900">
                        {data.label || option.label || `Answer ${data.sort_order}`}
                    </div>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setExpanded((current) => !current)}
                >
                    {expanded ? 'Hide' : 'Details'}
                </Button>
            </div>

            <div className="mt-3 grid gap-2.5 md:grid-cols-2">
                <ToggleField
                    checked={data.scoring_enabled}
                    onChange={(checked) =>
                        updateField('scoring_enabled', checked)
                    }
                    label="Score Answer"
                />
                {supportsJump ? (
                    <ToggleField
                        checked={data.jump_enabled}
                        onChange={(checked) =>
                            updateField('jump_enabled', checked)
                        }
                        label="Jump to Question"
                    />
                ) : null}
            </div>

            {expanded || data.scoring_enabled || (supportsJump && data.jump_enabled) || (supportsImage && option.image_url) ? (
                <div className="mt-4 space-y-4 border-t border-slate-200 pt-4">
                    {supportsImage ? (
                        <div className="space-y-2">
                            <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Image
                            </label>
                            <input
                                type="file"
                                accept="image/*"
                                onChange={(event) =>
                                    updateField(
                                        'image',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                className="block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
                            />
                            <div className="text-xs text-slate-500">
                                Image is required
                            </div>
                            {option.image_url ? (
                                <img
                                    src={option.image_url}
                                    alt={option.label}
                                    className="h-16 w-16 rounded-xl object-cover"
                                />
                            ) : null}
                        </div>
                    ) : null}

                    {data.scoring_enabled ? (
                        <div className="space-y-2">
                            <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                        <div className="space-y-2">
                            <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                                className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
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
                        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {fieldError(errors, [
                                'label',
                                'score_value',
                                'jump_to_question_id',
                            ])}
                        </div>
                    ) : null}
                </div>
            ) : null}

            <div className="mt-4 flex justify-end">
                <Button type="submit" size="sm" disabled={processing}>
                    Save Settings
                </Button>
            </div>
        </form>
    );
}

function useQuestionConfigPanel({
    scoreboard,
    question,
    questionTargets,
    questionTypeOptions,
}) {
    const QUESTION_AUTOSAVE_DELAY = 1500;
    const OPTION_AUTOSAVE_DELAY = 1500;
    const questionForm = useForm(buildQuestionFormData(question));
    const [activeTab, setActiveTab] = useState('question');
    const [questionDrafts, setQuestionDrafts] = useState({});
    const [optionDrafts, setOptionDrafts] = useState({});
    const [optionProcessingMap, setOptionProcessingMap] = useState({});
    const questionAutosaveTimerRef = useRef(null);
    const optionAutosaveTimersRef = useRef({});
    const lastSavedQuestionSnapshotRef = useRef('');
    const lastSavedOptionSnapshotsRef = useRef({});

    useEffect(() => {
        const nextData = question?.id
            ? questionDrafts[question.id] ?? buildQuestionFormData(question)
            : buildQuestionFormData(question);

        questionForm.setData(nextData);
        lastSavedQuestionSnapshotRef.current = serializeDraft(nextData);

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

            lastSavedOptionSnapshotsRef.current = question.options.reduce(
                (snapshots, option) => ({
                    ...snapshots,
                    [option.id]: serializeDraft(buildOptionDraft(option)),
                }),
                {},
            );
        } else {
            lastSavedOptionSnapshotsRef.current = {};
        }

        setActiveTab('question');
    }, [question?.id]);

    const setValue = (field, value) => {
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

    const saveOption = (optionId, overrides = {}) => {
        if (!question) {
            return;
        }

        const targetOption =
            question.options?.find((item) => item.id === optionId) ?? null;

        if (!targetOption || targetOption.is_virtual) {
            return;
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

        router.patch(
            route('admin.scoreboards.options.update', {
                assessment: scoreboard.id,
                question: question.id,
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
                },
                onFinish: () => {
                    setOptionProcessingMap((current) => ({
                        ...current,
                        [optionId]: false,
                    }));
                },
            },
        );
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

    const saveQuestion = () => {
        if (!question) {
            return;
        }

        questionForm.patch(
            route('admin.scoreboards.questions.update', {
                assessment: scoreboard.id,
                question: question.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    lastSavedQuestionSnapshotRef.current = serializeDraft(
                        questionForm.data,
                    );
                    setQuestionDrafts((current) => {
                        if (!question?.id) {
                            return current;
                        }

                        const nextDrafts = { ...current };
                        delete nextDrafts[question.id];
                        return nextDrafts;
                    });
                },
            },
        );
    };

    const submitQuestion = (event) => {
        event.preventDefault();
        saveQuestion();
    };

    const sharedPreviewValues = questionForm.data;
    const primaryError = Object.values(questionForm.errors)[0];
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
            question.question_type === 'yes_no_maybe' &&
            !questionForm.data.show_maybe_answer
        ) {
            options = options.filter((option) => option.internal_value !== 'maybe');
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

        return options;
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

    useEffect(() => {
        if (!question) {
            return undefined;
        }

        const serializedQuestion = serializeDraft(questionForm.data);

        if (serializedQuestion === lastSavedQuestionSnapshotRef.current) {
            return undefined;
        }

        if (questionForm.processing) {
            return undefined;
        }

        if (questionAutosaveTimerRef.current) {
            clearTimeout(questionAutosaveTimerRef.current);
        }

        questionAutosaveTimerRef.current = setTimeout(() => {
            saveQuestion();
        }, QUESTION_AUTOSAVE_DELAY);

        return () => {
            if (questionAutosaveTimerRef.current) {
                clearTimeout(questionAutosaveTimerRef.current);
            }
        };
    }, [question?.id, questionForm.data, questionForm.processing]);

    useEffect(() => {
        if (!question?.options?.length) {
            return undefined;
        }

        const activeOptionIds = new Set(question.options.map((option) => option.id));

        Object.keys(optionAutosaveTimersRef.current).forEach((optionId) => {
            if (!activeOptionIds.has(Number(optionId))) {
                clearTimeout(optionAutosaveTimersRef.current[optionId]);
                delete optionAutosaveTimersRef.current[optionId];
            }
        });

        question.options.forEach((option) => {
            const currentDraft = optionDrafts[option.id] ?? buildOptionDraft(option);
            const serializedDraft = serializeDraft(currentDraft);

            if (serializedDraft === lastSavedOptionSnapshotsRef.current[option.id]) {
                return;
            }

             if (optionProcessingMap[option.id]) {
                return;
            }

            if (optionAutosaveTimersRef.current[option.id]) {
                clearTimeout(optionAutosaveTimersRef.current[option.id]);
            }

            optionAutosaveTimersRef.current[option.id] = setTimeout(() => {
                saveOption(option.id);
            }, OPTION_AUTOSAVE_DELAY);
        });

        return () => {
            Object.values(optionAutosaveTimersRef.current).forEach((timer) =>
                clearTimeout(timer),
            );
        };
    }, [question?.id, optionDrafts, optionProcessingMap]);

    const questionTabContent = question ? (
        <form onSubmit={submitQuestion} className="space-y-4">
            <QuestionSection
                title="General"
                description="Core structure, type, and the essential content for this screen."
            >
                <div className="space-y-2">
                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Question Type
                    </label>
                    <select
                        value={questionForm.data.question_type}
                        onChange={(event) =>
                            setValue('question_type', event.target.value)
                        }
                        className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
                    >
                        {questionTypeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="space-y-2">
                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                <div className="space-y-3">
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
                </div>
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
                    <div className="space-y-2">
                        <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                            Jump to Question
                        </label>
                        <select
                            value={questionForm.data.jump_to_question_id}
                            onChange={(event) =>
                                setValue('jump_to_question_id', event.target.value)
                            }
                            className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
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
            </QuestionSection>

            <QuestionSection
                title="Type-specific Settings"
                description="Only the settings that matter for the active question type should live here."
            >
                {questionForm.data.question_type === 'yes_no_maybe' ? (
                    <ToggleField
                        checked={questionForm.data.show_maybe_answer}
                        onChange={(checked) =>
                            setValue('show_maybe_answer', checked)
                        }
                        label="Show Maybe Answer"
                    />
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
                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                            Answer Image Fit
                                        </label>
                                        <Input
                                            value={questionForm.data.answer_image_fit}
                                            onChange={(event) =>
                                                setValue(
                                                    'answer_image_fit',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                            Answers Per Row
                                        </label>
                                        <Input
                                            type="number"
                                            min="1"
                                            max="6"
                                            value={questionForm.data.answers_per_row}
                                            onChange={(event) =>
                                                setValue(
                                                    'answers_per_row',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </>
                        ) : null}

                        {questionForm.data.question_type ===
                        'multiple_choice_checkboxes' ? (
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        Min Count
                                    </label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={questionForm.data.min_count}
                                        onChange={(event) =>
                                            setValue('min_count', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        Max Count
                                    </label>
                                    <Input
                                        type="number"
                                        min="0"
                                        value={questionForm.data.max_count}
                                        onChange={(event) =>
                                            setValue('max_count', event.target.value)
                                        }
                                    />
                                </div>
                            </div>
                        ) : null}
                    </div>
                ) : null}

                {scaleTypes.includes(questionForm.data.question_type) ? (
                    <div className="space-y-3">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Score Range From
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
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                    Score Range To
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

                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                                {questionForm.data.question_type === 'divided_scale' ? (
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                            questionForm.data.question_type === 'linear_scale') ? (
                            <div className="grid gap-3 md:grid-cols-3">
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        Left
                                    </label>
                                    <Input
                                        value={questionForm.data.left_label}
                                        onChange={(event) =>
                                            setValue('left_label', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                        Center
                                    </label>
                                    <Input
                                        value={questionForm.data.center_label}
                                        onChange={(event) =>
                                            setValue('center_label', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                                    <div className="space-y-2">
                                        <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                        <div className="space-y-2">
                            <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Input Type
                            </label>
                            <Input
                                value={questionForm.data.input_type}
                                onChange={(event) =>
                                    setValue('input_type', event.target.value)
                                }
                                placeholder="text, email, textarea"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {primaryError}
                </div>
            ) : null}

            <div className="flex flex-wrap gap-3">
                <Button type="submit" disabled={questionForm.processing}>
                    Save Question
                </Button>
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
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600">
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
                            className="rounded-[22px] border border-dashed border-slate-300 bg-[#fbfaf6] px-4 py-4 text-sm text-slate-600"
                        >
                            <div className="font-medium text-slate-900">Other</div>
                            <div className="mt-1 leading-6">
                                Save the question first to create the special Other answer row, then continue configuring it here.
                            </div>
                        </div>
                    ) : (
                        <AnswerRow
                            key={option.id}
                            scoreboardId={scoreboard.id}
                            question={question}
                            option={option}
                            questionTargets={questionTargets}
                            draft={optionDrafts[option.id] ?? buildOptionDraft(option)}
                            setOptionValue={setOptionValue}
                        />
                    ),
                )}
            </div>
        ) : (
            <div className="space-y-4 rounded-[24px] border border-slate-200 bg-[#fbfaf6] px-4 py-5">
                <div className="space-y-2">
                    <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Scoring Category
                    </label>
                    <select
                        value={questionForm.data.scoring_category}
                        onChange={(event) =>
                            setValue('scoring_category', event.target.value)
                        }
                        className="flex h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
                    >
                        {scoringCategoryOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
        )
    ) : (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-600">
            Select a question first to manage its answers.
        </div>
    );

    const PanelContent = (
        <div className="space-y-4">
            <div className="grid h-auto w-full grid-cols-2 rounded-full border border-slate-200 bg-[#f3f1eb] p-1">
                <button
                    type="button"
                    onClick={() => setActiveTab('question')}
                    className={[
                        'rounded-full px-3 py-2 text-sm font-medium transition',
                        activeTab === 'question'
                            ? 'bg-white text-slate-900 shadow-[0_6px_16px_rgba(15,23,42,0.08)]'
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
                        'rounded-full px-3 py-2 text-sm font-medium transition',
                        activeTab === 'answers'
                            ? 'bg-white text-slate-900 shadow-[0_6px_16px_rgba(15,23,42,0.08)]'
                            : 'text-slate-500 hover:text-slate-700',
                    ].join(' ')}
                    aria-pressed={activeTab === 'answers'}
                >
                    Answers
                </button>
            </div>

            <div className="space-y-4">
                {activeTab === 'question' ? questionTabContent : answersTabContent}
            </div>
        </div>
    );

    const canvasError = primaryError;

    return {
        panel: (
            <section className="flex min-h-0 flex-col rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.06)] xl:sticky xl:top-6 xl:h-[calc(100vh-12rem)]">
                <div className="shrink-0 overflow-hidden rounded-t-[28px] border-b border-slate-200 bg-[linear-gradient(180deg,#faf9f6_0%,#f4f2ec_100%)] px-5 py-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-[#607362]">
                                <Settings2 className="size-3.5" />
                                Configuration
                            </div>
                            <h3 className="mt-2 text-base font-semibold text-slate-900">
                                Question / Answers
                            </h3>
                        </div>
                        {question ? (
                            <div className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500">
                                {formatQuestionType(question.question_type)}
                            </div>
                        ) : null}
                    </div>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto rounded-b-[28px] bg-[linear-gradient(180deg,#ffffff_0%,#fbfaf6_100%)] p-4 pb-10 [scrollbar-gutter:stable]">
                    {PanelContent}
                </div>
            </section>
        ),
        mobilePanel: PanelContent,
        values: sharedPreviewValues,
        visibleOptions,
        setValue,
        saveQuestion,
        questionProcessing: questionForm.processing,
        optionDrafts,
        setOptionValue,
        saveOption,
        optionProcessingMap,
        toggleCorrectOption,
        error: canvasError,
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
                <Button variant="outline">
                    <Palette className="size-4" />
                    Design Settings
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>Design Settings</SheetTitle>
                    <SheetDescription>
                        Global visual settings that apply to the scoreboard question flow.
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={submitDesign} className="mt-6 space-y-5">
                    <QuestionSection
                        title="Layout"
                        description="Global layout controls for the live builder canvas."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                                    placeholder="#f4f3ef"
                                />
                            </div>
                        </div>
                    </QuestionSection>

                    <QuestionSection
                        title="Spacing & Footer"
                        description="Control the frame around the screen without overloading the main builder panel."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                            <div className="space-y-2">
                                <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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

                        <div className="space-y-2">
                            <label className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                        <div className="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                            {Object.values(designForm.errors)[0]}
                        </div>
                    ) : null}

                    <div className="flex justify-end">
                        <Button type="submit" disabled={designForm.processing}>
                            Save Design Settings
                        </Button>
                    </div>
                </form>
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
            className="rounded-2xl border border-slate-200 bg-white p-4"
        >
            <div className="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_110px_110px_90px]">
                <Input
                    value={rangeForm.data.title}
                    onChange={(event) =>
                        rangeForm.setData('title', event.target.value)
                    }
                    placeholder="Result title"
                />
                <Input
                    type="number"
                    step="0.01"
                    value={rangeForm.data.min_score}
                    onChange={(event) =>
                        rangeForm.setData('min_score', event.target.value)
                    }
                    placeholder="Min"
                />
                <Input
                    type="number"
                    step="0.01"
                    value={rangeForm.data.max_score}
                    onChange={(event) =>
                        rangeForm.setData('max_score', event.target.value)
                    }
                    placeholder="Max"
                />
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

            <div className="mt-3">
                <Textarea
                    value={rangeForm.data.description}
                    onChange={(event) =>
                        rangeForm.setData('description', event.target.value)
                    }
                    className="min-h-24"
                    placeholder="Describe what this score range means."
                />
            </div>

            {Object.values(rangeForm.errors)[0] ? (
                <div className="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {Object.values(rangeForm.errors)[0]}
                </div>
            ) : null}

            <div className="mt-4 flex flex-wrap justify-end gap-3">
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
                <Button variant="outline">
                    <Target className="size-4" />
                    Result Ranges
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-3xl">
                <SheetHeader>
                    <SheetTitle>Result Ranges</SheetTitle>
                    <SheetDescription>
                        Configure range-based outcomes. If you leave this empty, the scoreboard can still go live and students will simply see the raw score.
                    </SheetDescription>
                </SheetHeader>

                <div className="mt-6 space-y-4">
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
                        <div className="rounded-3xl border border-dashed border-slate-300 bg-[#faf8f3] px-5 py-8">
                            <div className="max-w-xl">
                                <h4 className="text-lg font-semibold text-slate-900">
                                    No result ranges configured
                                </h4>
                                <p className="mt-2 text-sm leading-7 text-slate-600">
                                    This scoreboard can still be live. If you leave result ranges empty, the final student result will show the raw score only.
                                </p>
                            </div>
                        </div>
                    )}
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
    const selectedQuestion =
        questions.find((item) => item.id === selectedQuestionId) ?? questions[0] ?? null;

    const {
        panel,
        mobilePanel,
        values,
        visibleOptions,
        setValue,
        saveQuestion,
        questionProcessing,
        optionDrafts,
        setOptionValue,
        saveOption,
        optionProcessingMap,
        toggleCorrectOption,
        error,
    } = useQuestionConfigPanel({
        scoreboard,
        question: selectedQuestion,
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
                <div className="mx-auto max-w-[1660px] space-y-6 px-4 sm:px-6 lg:px-8">
                    {statusMessages[status] ? (
                        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                            {statusMessages[status]}
                        </div>
                    ) : null}

                    <BuilderWorkspaceBar
                        scoreboard={scoreboard}
                        selectedQuestion={selectedQuestion}
                        resultRanges={resultRanges}
                        design={design}
                        mobilePanel={mobilePanel}
                    />

                    <div className="space-y-5">
                        <div className="hidden rounded-[36px] border border-slate-200 bg-[#f5f3ed] p-4 shadow-[0_24px_60px_rgba(15,23,42,0.05)] xl:block">
                            <div
                                className="grid min-h-0 items-start gap-5"
                                style={{
                                    gridTemplateColumns:
                                        'minmax(248px,18%) minmax(680px,64%) minmax(300px,18%)',
                                }}
                            >
                                <ScoreboardNavigator
                                    scoreboardId={scoreboard.id}
                                    questions={questions}
                                    selectedQuestionId={selectedQuestion?.id}
                                />

                                <BuilderCanvas
                                    scoreboard={scoreboard}
                                    question={selectedQuestion}
                                    values={values}
                                    visibleOptions={visibleOptions}
                                    setValue={setValue}
                                    saveQuestion={saveQuestion}
                                    questionProcessing={questionProcessing}
                                    error={error}
                                    design={design}
                                    optionDrafts={optionDrafts}
                                    setOptionValue={setOptionValue}
                                    saveOption={saveOption}
                                    optionProcessingMap={optionProcessingMap}
                                    toggleCorrectOption={toggleCorrectOption}
                                />

                                <div className="min-h-0 xl:self-stretch">{panel}</div>
                            </div>
                        </div>

                        <div className="space-y-5 xl:hidden">
                            <ScoreboardNavigator
                                scoreboardId={scoreboard.id}
                                questions={questions}
                                selectedQuestionId={selectedQuestion?.id}
                            />

                            <BuilderCanvas
                                scoreboard={scoreboard}
                                question={selectedQuestion}
                                values={values}
                                visibleOptions={visibleOptions}
                                setValue={setValue}
                                saveQuestion={saveQuestion}
                                questionProcessing={questionProcessing}
                                error={error}
                                design={design}
                                optionDrafts={optionDrafts}
                                setOptionValue={setOptionValue}
                                saveOption={saveOption}
                                optionProcessingMap={optionProcessingMap}
                                toggleCorrectOption={toggleCorrectOption}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
