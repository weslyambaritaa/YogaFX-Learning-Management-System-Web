import { useEffect, useState } from 'react';

function reorderItems(items, sourceId, targetId, position) {
    const sourceIndex = items.findIndex((item) => item.id === sourceId);
    const targetIndex = items.findIndex((item) => item.id === targetId);

    if (sourceIndex === -1 || targetIndex === -1 || sourceIndex === targetIndex) {
        return items;
    }

    const nextItems = [...items];
    const [movedItem] = nextItems.splice(sourceIndex, 1);
    const targetIndexAfterRemoval = nextItems.findIndex((item) => item.id === targetId);
    const insertIndex = position === 'after'
        ? targetIndexAfterRemoval + 1
        : targetIndexAfterRemoval;

    nextItems.splice(insertIndex, 0, movedItem);

    return nextItems;
}

function resolveDropPosition(clientY, currentTarget) {
    const rect = currentTarget.getBoundingClientRect();

    return clientY >= rect.top + rect.height / 2 ? 'after' : 'before';
}

export default function useAdminTableReorder({
    items,
    enabled,
    onCommit,
}) {
    const [orderedItems, setOrderedItems] = useState(items);
    const [draggedItemId, setDraggedItemId] = useState(null);
    const [dropTarget, setDropTarget] = useState(null);
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        if (draggedItemId === null) {
            setOrderedItems(items);
        }
    }, [items, draggedItemId]);

    const resetInteraction = () => {
        setDraggedItemId(null);
        setDropTarget(null);
    };

    const commitReorder = (sourceId, targetId, position) => {
        if (!enabled || isSaving || sourceId === null || targetId === null || sourceId === targetId) {
            resetInteraction();

            return;
        }

        const previousItems = orderedItems;
        const nextItems = reorderItems(previousItems, sourceId, targetId, position);

        setOrderedItems(nextItems);
        setIsSaving(true);
        resetInteraction();

        Promise.resolve(onCommit({ sourceId, targetId, position }))
            .catch(() => {
                setOrderedItems(previousItems);
            })
            .finally(() => {
                setIsSaving(false);
            });
    };

    const updateDropTarget = (rowId, currentTarget, clientY) => {
        if (!enabled || isSaving || draggedItemId === null || draggedItemId === rowId) {
            setDropTarget(null);

            return;
        }

        setDropTarget({
            id: rowId,
            position: resolveDropPosition(clientY, currentTarget),
        });
    };

    const handleDragStart = (itemId) => {
        if (!enabled || isSaving) {
            return;
        }

        setDraggedItemId(itemId);
        setDropTarget(null);
    };

    const handleDragOver = (event, rowId) => {
        if (!enabled || isSaving) {
            return;
        }

        event.preventDefault();
        updateDropTarget(rowId, event.currentTarget, event.clientY);
    };

    const handleDrop = (event, rowId) => {
        if (!enabled || isSaving) {
            return;
        }

        event.preventDefault();

        const position = resolveDropPosition(event.clientY, event.currentTarget);

        commitReorder(draggedItemId, rowId, position);
    };

    const handleTouchStart = (itemId) => (event) => {
        if (!enabled || isSaving) {
            return;
        }

        event.preventDefault();
        setDraggedItemId(itemId);
        setDropTarget(null);
    };

    const handleTouchMove = (event) => {
        if (!enabled || isSaving || draggedItemId === null) {
            return;
        }

        event.preventDefault();

        const touch = event.touches[0];

        if (!touch) {
            return;
        }

        const targetElement = document
            .elementFromPoint(touch.clientX, touch.clientY)
            ?.closest('[data-reorder-row="true"]');

        if (!(targetElement instanceof HTMLElement)) {
            setDropTarget(null);

            return;
        }

        const rowId = Number(targetElement.dataset.rowId);

        if (!Number.isInteger(rowId)) {
            setDropTarget(null);

            return;
        }

        updateDropTarget(rowId, targetElement, touch.clientY);
    };

    const handleTouchEnd = () => {
        if (!enabled || isSaving || draggedItemId === null || !dropTarget) {
            resetInteraction();

            return;
        }

        commitReorder(draggedItemId, dropTarget.id, dropTarget.position);
    };

    const handleTouchCancel = () => {
        resetInteraction();
    };

    const getRowProps = (rowId) => ({
        'data-reorder-row': 'true',
        'data-row-id': String(rowId),
        onDragOver: (event) => handleDragOver(event, rowId),
        onDrop: (event) => handleDrop(event, rowId),
    });

    const getHandleProps = (itemId) => ({
        draggable: enabled && !isSaving,
        onDragStart: () => handleDragStart(itemId),
        onDragEnd: resetInteraction,
        onTouchStart: handleTouchStart(itemId),
        onTouchMove: handleTouchMove,
        onTouchEnd: handleTouchEnd,
        onTouchCancel: handleTouchCancel,
    });

    return {
        items: orderedItems,
        draggedItemId,
        dropTarget,
        isSaving,
        getRowProps,
        getHandleProps,
    };
}
