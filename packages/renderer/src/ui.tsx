import {
  Activity,
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Archive,
  ArrowDown,
  ArrowRight,
  ArrowUp,
  ArrowUpDown,
  Award,
  Bell,
  Bold,
  Bookmark,
  BookOpen,
  Bot,
  Building2,
  Calendar,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  CircleAlert,
  CircleCheck,
  CircleHelp,
  ClipboardList,
  Clock,
  Code,
  Columns3,
  Copy,
  Crop,
  Database,
  Download,
  Ellipsis,
  ExternalLink,
  Eye,
  EyeOff,
  FileCode,
  Files,
  FileText,
  Folder,
  FolderOpen,
  Gift,
  Globe,
  GripVertical,
  Hammer,
  History,
  Heading2,
  Heading3,
  Heading4,
  Highlighter,
  Image,
  ImagePlus,
  Info,
  Italic,
  LayoutDashboard,
  LayoutGrid,
  Link2,
  List,
  ListFilter,
  ListOrdered,
  Lock,
  LogIn,
  LogOut,
  type LucideIcon,
  Mail,
  Map as MapIcon,
  MapPin,
  Maximize2,
  Megaphone,
  Menu as MenuIcon,
  MessageSquare,
  Minimize2,
  Minus,
  Monitor,
  Moon,
  Network,
  Package,
  Palette,
  PanelLeft,
  PanelLeftClose,
  PanelLeftOpen,
  PanelTop,
  Pencil,
  Plus,
  QrCode,
  Quote,
  Redo2,
  RemoveFormatting,
  Replace,
  Rows3,
  Rows4,
  RotateCcw,
  RotateCw,
  Save,
  Search,
  Send,
  Settings,
  Shield,
  SquareCode,
  Star,
  Strikethrough,
  Subscript,
  Sun,
  Superscript,
  Table,
  TableCellsMerge,
  TableCellsSplit,
  Tag,
  Ticket,
  Trash2,
  TriangleAlert,
  Truck,
  Type,
  Underline,
  Undo2,
  Upload,
  User,
  Users,
  Video,
  Wrench,
  X,
  ZoomIn,
  ZoomOut,
} from 'lucide-react';
import { useCallback, useEffect, useState, type RefCallback } from 'react';

/** Icon names accepted by ->icon() in PHP. */
const ICONS: Record<string, LucideIcon> = {
  activity: Activity,
  'arrow-down': ArrowDown,
  'arrow-right': ArrowRight,
  history: History,
  'arrow-up': ArrowUp,
  sort: ArrowUpDown,
  star: Star,
  bell: Bell,
  bold: Bold,
  calendar: Calendar,
  check: Check,
  'chevron-down': ChevronDown,
  'chevron-left': ChevronLeft,
  'chevron-right': ChevronRight,
  'chevron-up': ChevronUp,
  alert: CircleAlert,
  success: CircleCheck,
  code: Code,
  columns: Columns3,
  copy: Copy,
  download: Download,
  external: ExternalLink,
  more: Ellipsis,
  eye: Eye,
  'eye-off': EyeOff,
  file: FileText,
  filter: ListFilter,
  folder: Folder,
  'folder-open': FolderOpen,
  grip: GripVertical,
  image: Image,
  info: Info,
  italic: Italic,
  link: Link2,
  list: List,
  'list-ordered': ListOrdered,
  warning: TriangleAlert,
  dashboard: LayoutDashboard,
  grid: LayoutGrid,
  'log-in': LogIn,
  'log-out': LogOut,
  mail: Mail,
  minus: Minus,
  monitor: Monitor,
  moon: Moon,
  pencil: Pencil,
  plus: Plus,
  quote: Quote,
  redo: Redo2,
  save: Save,
  search: Search,
  settings: Settings,
  shield: Shield,
  strike: Strikethrough,
  sun: Sun,
  trash: Trash2,
  underline: Underline,
  undo: Undo2,
  upload: Upload,
  user: User,
  users: Users,
  x: X,
  ticket: Ticket,
  building: Building2,
  book: BookOpen,
  tag: Tag,
  gift: Gift,
  'qr-code': QrCode,
  hammer: Hammer,
  'map-pin': MapPin,
  message: MessageSquare,
  database: Database,
  send: Send,
  files: Files,
  sitemap: Network,
  bot: Bot,
  wrench: Wrench,
  lock: Lock,
  award: Award,
  bookmark: Bookmark,
  help: CircleHelp,
  archive: Archive,
  megaphone: Megaphone,
  package: Package,
  truck: Truck,
  clipboard: ClipboardList,
  video: Video,
  globe: Globe,
  'align-left': AlignLeft,
  'align-center': AlignCenter,
  'align-right': AlignRight,
  table: Table,
  'file-code': FileCode,
  crop: Crop,
  rotate: RotateCw,
  'rotate-left': RotateCcw,
  'zoom-in': ZoomIn,
  'zoom-out': ZoomOut,
  'subscript': Subscript,
  'superscript': Superscript,
  'palette': Palette,
  'highlighter': Highlighter,
  'eraser': RemoveFormatting,
  'align-justify': AlignJustify,
  'maximize': Maximize2,
  'minimize': Minimize2,
  'cells-merge': TableCellsMerge,
  'cells-split': TableCellsSplit,
  'replace': Replace,
  'type': Type,
  'image-plus': ImagePlus,
  'header-row': PanelTop,
  'header-column': PanelLeft,
  'code-block': SquareCode,
  'heading-2': Heading2,
  'heading-3': Heading3,
  'heading-4': Heading4,
  map: MapIcon,
  menu: MenuIcon,
  clock: Clock,
  'sidebar-close': PanelLeftClose,
  'sidebar-open': PanelLeftOpen,
  'density-comfortable': Rows3,
  'density-compact': Rows4,
};

export function Icon({ name, className = 'size-4' }: { name?: string | null; className?: string }) {
  const Component = name ? ICONS[name] : undefined;
  if (!Component) {
    if (name && import.meta.env.DEV) console.warn(`Vanda: unknown icon "${name}". Known: ${Object.keys(ICONS).join(', ')}`);
    return null;
  }
  return <Component className={className} strokeWidth={1.75} aria-hidden="true" />;
}

export function cx(...classes: (string | false | null | undefined)[]): string {
  return classes.filter(Boolean).join(' ');
}

/** Red border and ring of a field with an error, as .o-input.is-invalid in the design. */
export const invalidClass = 'border-danger focus:border-danger ring-3 ring-[color-mix(in_oklch,var(--v-danger)_16%,transparent)]';

/** Shared look of text inputs, select triggers and pickers. */
/**
 * A field marked ->readonly(): it shows its value like text on a quiet ground, without the frame
 * and the chevrons of something to change (a disabled field is dimmed instead).
 */
export const readonlyClass =
  'border-transparent bg-surface-2/70 shadow-none cursor-default focus:border-transparent focus:ring-0 disabled:bg-surface-2/70 disabled:text-fg disabled:cursor-default data-[disabled]:text-fg';

export const controlClass = cx(
  'h-(--v-control) w-full rounded-lg border border-control-line bg-surface px-2.5 text-[14px] text-fg shadow-sm transition',
  'placeholder:text-fg-3 focus:outline-none focus:border-accent focus:ring-3 focus:ring-ring',
  'disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-fg-3 disabled:shadow-none',
);

const TONES = ['#12805C', '#2563EB', '#BE185D', '#C2410C', '#0F766E', '#7C3AED'];

/** Initials avatar (or a photo); the hue comes from the server so it is stable per person. */
export function Avatar({ initials, hue = 0, size = 'md', image }: { initials: string; hue?: number; size?: 'sm' | 'md' | 'lg'; image?: string | null }) {
  const color = TONES[hue % TONES.length];
  const box = size === 'sm' ? 'size-6 text-[10.5px]' : size === 'lg' ? 'size-12 text-[17px]' : 'size-8 text-[12px]';
  if (image) return <img src={image} alt="" className={cx('shrink-0 rounded-full object-cover', box)} />;
  return (
    <span
      aria-hidden="true"
      className={cx('grid shrink-0 place-items-center rounded-full font-semibold', box)}
      style={{ background: `color-mix(in oklch, ${color} 14%, var(--v-surface))`, color: `color-mix(in oklch, ${color} 72%, var(--v-fg))` }}
    >
      {initials}
    </span>
  );
}

const VARIANTS: Record<string, string> = {
  primary: 'bg-accent text-white shadow-primary hover:bg-accent-hover',
  secondary: 'bg-surface text-fg border border-line-strong shadow-sm hover:bg-surface-2',
  ghost: 'text-fg-2 hover:bg-surface-2 hover:text-fg',
  danger: 'bg-danger text-white hover:opacity-90',
  'danger-soft': 'bg-danger-soft text-danger-fg hover:bg-[color-mix(in_oklch,var(--v-danger)_16%,var(--v-surface))]',
  link: 'text-accent-fg hover:underline underline-offset-4 px-0 h-auto',
};

export function buttonClass(variant = 'secondary', size: 'sm' | 'md' = 'md'): string {
  return cx(
    'inline-flex items-center justify-center gap-1.5 rounded-lg font-medium whitespace-nowrap transition-colors',
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none cursor-pointer select-none',
    size === 'sm' ? 'h-7 px-2.5 text-[13px] rounded-md' : 'h-(--v-control) px-3 text-[13.5px]',
    VARIANTS[variant] ?? VARIANTS.secondary,
  );
}

export function Spinner({ className = 'size-3.5' }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cx('inline-block rounded-full border-2 border-current border-r-transparent', className)}
      style={{ animation: 'vanda-spin 0.7s linear infinite' }}
    />
  );
}

export function Kbd({ children, inverted = false }: { children: string; inverted?: boolean }) {
  return (
    <kbd
      className={cx(
        // Phones have no keyboard shortcuts to hint at.
        'inline-flex h-[18px] items-center rounded border px-[5px] font-mono text-[11px] font-medium max-md:hidden',
        inverted ? 'border-white/25 bg-white/15 text-white/90' : 'border-line bg-surface-2 text-fg-3',
      )}
    >
      {children}
    </kbd>
  );
}

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

/** "mod+s" → "⌘S" on macOS, "Ctrl+S" elsewhere. */
export function formatHotkey(hotkey: string): string {
  return hotkey
    .split('+')
    .map((part) => (part === 'mod' ? (isMac ? '⌘' : 'Ctrl+') : part.toUpperCase()))
    .join('');
}

export function matchesHotkey(event: KeyboardEvent, hotkey: string): boolean {
  const parts = hotkey.toLowerCase().split('+');
  const key = parts[parts.length - 1];
  const mod = parts.includes('mod');
  return event.key.toLowerCase() === key && (!mod || (isMac ? event.metaKey : event.ctrlKey)) && event.shiftKey === parts.includes('shift');
}

/** Phones and narrow windows: below Tailwind's md breakpoint (768px). */
export const MOBILE_QUERY = '(max-width: 767.98px)';

/** Follows a media query, e.g. useMediaQuery(MOBILE_QUERY). */
export function useMediaQuery(query: string): boolean {
  const [matches, setMatches] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);

  useEffect(() => {
    const list = window.matchMedia(query);
    const update = () => setMatches(list.matches);
    update();
    list.addEventListener('change', update);
    return () => list.removeEventListener('change', update);
  }, [query]);

  return matches;
}

/**
 * A horizontal scroller (tabs, a toolbar) that hides part of its row: data-fade="left", "right" or
 * "both" says on which side something waits, and the .vanda-fade-x class fades that edge, so the
 * row never looks complete when it is not. The selected item (aria-selected, or aria-current for links) is brought into view.
 *
 *     const ref = useOverflowFade<HTMLDivElement>();
 *     <div ref={ref} role="tablist" className="vanda-fade-x flex overflow-x-auto">…</div>
 */
export function useOverflowFade<T extends HTMLElement>(): RefCallback<T> {
  // A ref callback (with React 19's cleanup), so a scroller that appears later is watched too.
  return useCallback((element: T | null) => {
    if (!element) return;
    const update = () => {
      // Measured on the screen, so it holds in both writing directions (scrollLeft is negative in RTL).
      const box = element.getBoundingClientRect();
      const first = element.firstElementChild?.getBoundingClientRect();
      const last = element.lastElementChild?.getBoundingClientRect();
      const left = first ? first.left < box.left - 1 : false;
      const right = last ? last.right > box.right + 1 : false;
      const fade = left && right ? 'both' : left ? 'left' : right ? 'right' : '';
      if (fade) element.dataset.fade = fade;
      else delete element.dataset.fade;
    };
    const selected = element.querySelector<HTMLElement>('[aria-selected="true"], [aria-current="page"]');
    if (selected) {
      // By hand: scrollIntoView would also move the page's own scroll containers.
      const box = element.getBoundingClientRect();
      const item = selected.getBoundingClientRect();
      if (item.left < box.left || item.right > box.right) element.scrollLeft += item.left - box.left - (box.width - item.width) / 2;
    }
    update();
    const observer = new ResizeObserver(update);
    observer.observe(element);
    element.addEventListener('scroll', update, { passive: true });
    return () => {
      observer.disconnect();
      element.removeEventListener('scroll', update);
    };
  }, []);
}
