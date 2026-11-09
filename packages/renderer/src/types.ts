/** Page IR produced by Vanda\Screen\ScreenRuntime::ir() — schema "vanda/v1". */

export type State = Record<string, unknown>;

export type ExprNode =
  | { field: string }
  | { value: unknown }
  | { op: string; args: ExprNode[] };

export interface BaseNode {
  type: string;
  id: string;
  visibleWhen?: ExprNode;
  dependsOn?: string[];
  source?: string;
  children?: UiNode[];
  [key: string]: unknown;
}

export interface FieldNode extends BaseNode {
  path: string;
  label?: string;
  help?: string;
  /** Field::help($text, inline: true): the hint stays as text under the field instead of an «i». */
  helpInline?: boolean;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  live?: number;
  liveValidation?: boolean;
  requiredWhen?: ExprNode;
  disabledWhen?: ExprNode;
  computed?: ExprNode;
  /** Set by the renderer for a field in a table cell: the column header already names it. */
  labelHidden?: boolean;
}

export interface Option {
  value: string | number;
  label: string;
  /** Muted text after the label, e.g. "UTC+01:00". */
  hint?: string;
}

export interface ActionRef {
  name: string;
  args?: Record<string, unknown>;
  token: string;
  bulk?: boolean;
  /** Names of arguments the browser supplies (ActionCall::withInput). */
  inputs?: string[];
}

export type UiNode = BaseNode;

export interface MenuItem {
  label: string;
  href?: string;
  icon?: string;
  group?: string;
  badge?: string | number;
  active?: boolean;
  /** MenuItem::pinned(): one of the (at most three) tabs of the phone's tab bar. */
  pinned?: boolean;
}

export interface Crumb {
  label: string;
  href?: string;
}

/** How much fits on a screen (config('vanda.density'), the user's choice under the menu). */
export type Density = 'comfortable' | 'compact';

export interface Page {
  schema: 'vanda/v1';
  screen: string;
  url: string;
  chrome?: 'panel' | 'card';
  title: string | null;
  description: string | null;
  avatar?: { name: string; initials: string; hue: number; image?: string } | null;
  badge?: UiNode | null;
  breadcrumbs?: Crumb[];
  commandBar: UiNode[];
  layout: UiNode[];
  state: State;
  snapshot: string;
  app: {
    name: string;
    brand: string;
    menu: MenuItem[];
    /** URL of the ⌘K records search, when Vanda::search() providers exist. */
    search?: string;
    /** The bell: Vanda's notification endpoints and the unread count. */
    notifications?: { url: string; read: string; unread: number } | null;
    user: { name: string; email: string; avatar: { initials: string; hue: number; image?: string }; logout?: string; impersonator?: { name: string; stop: string }; profile?: string } | null;
    /** config('vanda.auth_background'): a URL, null for Vanda's own picture, false for none. */
    authBackground?: string | false | null;
    /** config('vanda.density'): the panel's sizes unless the user picked the other one. */
    density?: Density;
    /** Strips across the top of every page, e.g. a weak password on production. */
    alerts?: { tone: string; text: string; link?: { label: string; href: string } }[];
  };
}

export interface PatchOp {
  op: 'replace';
  path: string;
  value: unknown;
}

export interface ReactResponse {
  patches: PatchOp[];
  errors: Record<string, string[]>;
  snapshot: string;
}

export interface ActionResponse {
  toast?: { message: string; tone: string };
  redirect?: string;
  openModal?: string;
  page?: Page;
  snapshot?: string;
  errors?: Record<string, string[]>;
}
