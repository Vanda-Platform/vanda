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
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  readonly?: boolean;
  live?: number;
  liveValidation?: boolean;
  requiredWhen?: ExprNode;
  disabledWhen?: ExprNode;
  computed?: ExprNode;
}

export interface Option {
  value: string | number;
  label: string;
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
}

export interface Crumb {
  label: string;
  href?: string;
}

export interface Page {
  schema: 'vanda/v1';
  screen: string;
  url: string;
  chrome?: 'panel' | 'card';
  title: string | null;
  description: string | null;
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
    user: { name: string; email: string; logout?: string } | null;
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
