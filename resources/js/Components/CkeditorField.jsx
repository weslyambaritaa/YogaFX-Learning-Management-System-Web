import { forwardRef, useImperativeHandle, useRef } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import {
    Autoformat,
    AutoImage,
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    GeneralHtmlSupport,
    Heading,
    Image,
    ImageCaption,
    ImageInline,
    ImageStyle,
    ImageTextAlternative,
    ImageToolbar,
    Italic,
    Link,
    List,
    ListProperties,
    Paragraph,
    PasteFromOffice,
    SourceEditing,
    Strikethrough,
    Table,
    TableToolbar,
    Underline,
} from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';

const editorConfig = {
    licenseKey: 'GPL',
    plugins: [
        Autoformat,
        AutoImage,
        BlockQuote,
        Bold,
        Essentials,
        GeneralHtmlSupport,
        Heading,
        Image,
        ImageCaption,
        ImageInline,
        ImageStyle,
        ImageTextAlternative,
        ImageToolbar,
        Italic,
        Link,
        List,
        ListProperties,
        Paragraph,
        PasteFromOffice,
        SourceEditing,
        Strikethrough,
        Table,
        TableToolbar,
        Underline,
    ],
    toolbar: {
        items: [
            'undo',
            'redo',
            '|',
            'heading',
            '|',
            'bold',
            'italic',
            'underline',
            'strikethrough',
            '|',
            'link',
            'imageTextAlternative',
            'blockQuote',
            'insertTable',
            '|',
            'bulletedList',
            'numberedList',
            '|',
            'sourceEditing',
        ],
        shouldNotGroupWhenFull: true,
    },
    heading: {
        options: [
            {
                model: 'paragraph',
                title: 'Paragraph',
                class: 'ck-heading_paragraph',
            },
            {
                model: 'heading2',
                view: 'h2',
                title: 'Heading 2',
                class: 'ck-heading_heading2',
            },
            {
                model: 'heading3',
                view: 'h3',
                title: 'Heading 3',
                class: 'ck-heading_heading3',
            },
        ],
    },
    list: {
        properties: {
            styles: true,
            startIndex: true,
            reversed: true,
        },
    },
    table: {
        contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
    },
    image: {
        toolbar: [
            'imageTextAlternative',
            '|',
            'imageStyle:inline',
            'imageStyle:block',
            'imageStyle:side',
        ],
    },
    htmlSupport: {
        allow: [
            {
                name: /.*/,
                attributes: true,
                classes: true,
                styles: true,
            },
        ],
    },
};

const CkeditorField = forwardRef(function CkeditorField({
    value = '',
    onChange,
    disabled = false,
    invalid = false,
    className = '',
}, ref) {
    const editorRef = useRef(null);

    useImperativeHandle(ref, () => ({
        insertHtml(html) {
            const editor = editorRef.current;

            if (!editor || typeof html !== 'string' || html.trim() === '') {
                return false;
            }

            const currentData = editor.getData();
            const separator = currentData.trim() === '' ? '' : '<p>&nbsp;</p>';
            const nextData = `${currentData}${separator}${html}`;

            editor.setData(nextData);
            onChange?.(nextData);
            editor.editing.view.focus();

            return true;
        },
    }), []);

    return (
        <div className={`ckeditor-field ${invalid ? 'ckeditor-field-invalid' : ''} ${className}`.trim()}>
            <CKEditor
                editor={ClassicEditor}
                disabled={disabled}
                config={editorConfig}
                data={value ?? ''}
                onReady={(editor) => {
                    editorRef.current = editor;
                }}
                onChange={(_, editor) => {
                    onChange?.(editor.getData());
                }}
                onAfterDestroy={() => {
                    editorRef.current = null;
                }}
            />
        </div>
    );
});

export default CkeditorField;
