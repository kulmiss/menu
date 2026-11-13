import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

type SortableListProps = {
  ids: number[]
  onReorder: (ids: number[]) => void
  children: React.ReactNode
}

/**
 * A vertical list reordered by dragging a handle: mouse, touch (after a
 * short press, so scrolling still works) and keyboard (space, arrows).
 */
export function SortableList({ ids, onReorder, children }: SortableListProps) {
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(TouchSensor, {
      activationConstraint: { delay: 200, tolerance: 8 },
    }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  function onDragEnd({ active, over }: DragEndEvent) {
    if (!over || active.id === over.id) return
    const from = ids.indexOf(Number(active.id))
    const to = ids.indexOf(Number(over.id))
    if (from < 0 || to < 0) return
    onReorder(arrayMove(ids, from, to))
  }

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragEnd={onDragEnd}
    >
      <SortableContext items={ids} strategy={verticalListSortingStrategy}>
        {children}
      </SortableContext>
    </DndContext>
  )
}

type SortableItemProps = {
  id: number
  disabled?: boolean
  handleLabel: string
  className?: string
  /** Renders the row; put `handle` where the grip should be. */
  children: (handle: React.ReactNode) => React.ReactNode
  as?: 'li' | 'div'
}

export function SortableItem({
  id,
  disabled,
  handleLabel,
  className,
  children,
  as: Tag = 'li',
}: SortableItemProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id, disabled })

  const handle = disabled ? null : (
    <Button
      ref={setActivatorNodeRef}
      type='button'
      variant='ghost'
      size='icon'
      className='size-11 shrink-0 cursor-grab touch-none text-muted-foreground sm:size-8'
      aria-label={handleLabel}
      {...attributes}
      {...listeners}
    >
      <GripVertical />
    </Button>
  )

  return (
    <Tag
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        className,
        isDragging && 'relative z-10 opacity-80 shadow-lg'
      )}
    >
      {children(handle)}
    </Tag>
  )
}
