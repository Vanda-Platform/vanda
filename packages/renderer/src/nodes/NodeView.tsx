import { lazy, Suspense, type ComponentType } from 'react';
import { isTrue } from '../expr';
import { useSnapshot } from '../store';
import type { FieldNode, UiNode } from '../types';
import { cx } from '../ui';
import { ButtonView, DropDownView, LinkView, ModalToggleView } from './actions';
import { ActivityView } from './activity';
import { ComboField } from './combo';
import {
  CheckboxField,
  ColorField,
  FieldFrame,
  InputField,
  LabelField,
  NumberRangeField,
  PasswordField,
  RadioField,
  RangeField,
  SegmentedField,
  SelectField,
  SwitchField,
  TextAreaField,
} from './fields';
import { ActionListView, ActionRowView, AlertView, BadgeView, CardView, EmptyStateView, GridView, LegendView, MetricsView, ModalView, PersonaView, SectionView, TabsView, TextView, ViewIsland } from './layout';
import { KeyValueField, PermissionsField, RepeaterField } from './structured';
import { TableView } from './table';
import { UploadField } from './upload';
import { UtmField } from './utm';

type NodeComponent = ComponentType<{ node: any; compact?: boolean }>;

/** A component from a separate chunk, loaded the first time a screen uses it. */
function chunk<K extends string>(load: () => Promise<Record<K, NodeComponent>>, name: K): NodeComponent {
  return lazy(() => load().then((module): { default: NodeComponent } => ({ default: module[name] }))) as NodeComponent;
}

const dates = () => import('./dates');
const code = () => import('./code');
const richtext = () => import('./richtext');
const tree = () => import('./tree');
const charts = () => import('./charts');
const map = () => import('./map');

/**
 * Node type → component. The PHP side never sends HTML, only these types.
 * A custom component is registered here under the type its PHP class returns.
 */
const REGISTRY: Record<string, NodeComponent> = {
  section: SectionView,
  activity: ActivityView,
  card: CardView,
  grid: GridView,
  tabs: TabsView,
  modal: ModalView,
  table: TableView,
  treetable: chunk(tree, 'TreeTableView'),
  legend: LegendView,
  metrics: MetricsView,
  chart: chunk(charts, 'ChartView'),
  alert: AlertView,
  empty: EmptyStateView,
  actionlist: ActionListView,
  actionrow: ActionRowView,
  persona: PersonaView,
  text: TextView,
  view: ViewIsland,
  badge: BadgeView,
  input: InputField,
  password: PasswordField,
  textarea: TextAreaField,
  select: SelectField,
  relation: ({ node }) => <ComboField node={node} remote />,
  treeselect: chunk(tree, 'TreeSelectField'),
  switch: SwitchField,
  checkbox: CheckboxField,
  radio: RadioField,
  segmented: SegmentedField,
  date: chunk(dates, 'DateField'),
  daterange: chunk(dates, 'DateRangeField'),
  range: RangeField,
  numberrange: NumberRangeField,
  color: ColorField,
  repeater: RepeaterField,
  keyvalue: KeyValueField,
  permissions: PermissionsField,
  upload: UploadField,
  map: chunk(map, 'MapField'),
  utm: UtmField,
  label: LabelField,
  richtext: chunk(richtext, 'RichTextField'),
  code: chunk(code, 'CodeField'),
  markdown: chunk(code, 'MarkdownField'),
  button: ({ node, compact }) => <ButtonView node={node} size={compact ? 'sm' : 'md'} />,
  link: ({ node, compact }) => <LinkView node={node} size={compact ? 'sm' : 'md'} />,
  modaltoggle: ModalToggleView,
  dropdown: DropDownView,
};

export function NodeView({ node, compact = false }: { node: UiNode; compact?: boolean }) {
  const visible = useSnapshot((s) => (node.visibleWhen ? isTrue(node.visibleWhen, s.page.state) : true));
  if (!visible) return null;

  const Component = REGISTRY[node.type];
  if (!Component) {
    return (
      <div role="alert" className="rounded-lg border border-dashed border-danger px-3 py-2 font-mono text-[12.5px] text-danger-fg">
        Unknown node type "{node.type}" ({node.id}){node.source ? ` at ${node.source}` : ''}
      </div>
    );
  }

  return (
    <Suspense fallback={<Placeholder node={node} />}>
      <Component node={node} compact={compact} />
    </Suspense>
  );
}

const pulse = 'animate-pulse rounded-lg bg-surface-2 motion-reduce:animate-none';

/**
 * What a lazy component shows while its chunk loads: its label and a box of the size it will
 * have, so the page does not jump when the editor, map or chart arrives.
 */
function Placeholder({ node }: { node: UiNode }) {
  const sized = node as FieldNode & { minHeight?: number; height?: number; title?: string; description?: string; span?: number };
  const field = (height: number) => (
    <FieldFrame node={sized} inputId={`${node.id}-loading`} required={false}>
      <div aria-busy="true" className={pulse} style={{ height }} />
    </FieldFrame>
  );

  switch (node.type) {
    case 'richtext':
      return field((sized.minHeight ?? 200) + RICHTEXT_CHROME);
    case 'code':
      return field((sized.minHeight ?? 160) + CODE_CHROME);
    case 'markdown':
      return field((sized.minHeight ?? 160) + MARKDOWN_CHROME);
    case 'map':
      return field((sized.height ?? 300) + MAP_CHROME);
    case 'chart':
      return (
        <section aria-busy="true" className={cx('flex min-w-0 flex-col gap-4 rounded-xl border border-line bg-surface p-5', CHART_SPANS[sized.span ?? 1])}>
          <div className="flex flex-col gap-0.5">
            <h2 className="text-[15px] font-semibold">{sized.title}</h2>
            {sized.description && <span className="text-[13.5px] text-fg-3">{sized.description}</span>}
          </div>
          <div className={pulse} style={{ height: sized.height ?? 220 }} />
        </section>
      );
    case 'treetable':
      return <div aria-busy="true" className={cx(pulse, 'h-48')} />;
    default:
      return 'path' in node ? field(36) : <div aria-busy="true" className={cx(pulse, 'h-(--v-control)')} />;
  }
}

/** Height of each lazy editor around its text area: toolbars, tabs, borders (measured in the browser). */
const RICHTEXT_CHROME = 100;
const CODE_CHROME = 28;
const MARKDOWN_CHROME = 39;
const MAP_CHROME = 97;
const CHART_SPANS: Record<number, string> = { 2: 'md:col-span-2', 3: 'md:col-span-3', 4: 'md:col-span-4' };

export function NodeList({ nodes }: { nodes: UiNode[] }) {
  return nodes.map((node) => <NodeView key={node.id} node={node} />);
}
