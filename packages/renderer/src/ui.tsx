import {
  Bell,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CircleAlert,
  CircleCheck,
  FileText,
  LayoutDashboard,
  LogIn,
  type LucideIcon,
  Mail,
  Monitor,
  Moon,
  Pencil,
  Plus,
  Save,
  Search,
  Settings,
  Shield,
  Sun,
  Trash2,
  User,
  Users,
  X,
} from 'lucide-react';

/** Icon names accepted by ->icon() in PHP. */
const ICONS: Record<string, LucideIcon> = {
  bell: Bell,
  check: Check,
  'chevron-down': ChevronDown,
  'chevron-left': ChevronLeft,
  'chevron-right': ChevronRight,
  alert: CircleAlert,
  success: CircleCheck,
  file: FileText,
  dashboard: LayoutDashboard,
  'log-in': LogIn,
  mail: Mail,
  monitor: Monitor,
  moon: Moon,
  pencil: Pencil,
  plus: Plus,
  save: Save,
  search: Search,
  settings: Settings,
  shield: Shield,
  sun: Sun,
  trash: Trash2,
  user: User,
  users: Users,
  x: X,
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

const VARIANTS: Record<string, string> = {
  primary: 'bg-accent text-white shadow-sm hover:bg-accent-hover',
  secondary: 'bg-surface text-fg border border-line-strong shadow-sm hover:bg-surface-2',
  ghost: 'text-fg-2 hover:bg-surface-2 hover:text-fg',
  danger: 'bg-danger text-white hover:opacity-90',
  link: 'text-accent-fg hover:underline underline-offset-4 px-0 h-auto',
};

export function buttonClass(variant = 'secondary', size: 'sm' | 'md' = 'md'): string {
  return cx(
    'inline-flex items-center justify-center gap-1.5 rounded-lg font-medium whitespace-nowrap transition-colors',
    'disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer select-none',
    size === 'sm' ? 'h-7 px-2.5 text-[13px] rounded-md' : 'h-9 px-3 text-[13.5px]',
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
        'inline-flex h-[18px] items-center rounded border px-1.5 font-mono text-[11px] font-medium',
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
