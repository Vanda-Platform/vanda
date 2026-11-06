import { lazy, Suspense, type ComponentType } from 'react';
import { isTrue } from '../expr';
import { useSnapshot } from '../store';
import type { UiNode } from '../types';
import { ButtonView, DropDownView, LinkView, ModalToggleView } from './actions';
import { ComboField } from './combo';
import {
  CheckboxField,
  ColorField,
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
    <Suspense fallback={<div aria-busy="true" className="h-9 animate-pulse rounded-lg bg-surface-2" />}>
      <Component node={node} compact={compact} />
    </Suspense>
  );
}

export function NodeList({ nodes }: { nodes: UiNode[] }) {
  return nodes.map((node) => <NodeView key={node.id} node={node} />);
}
