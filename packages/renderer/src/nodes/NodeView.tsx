import type { ComponentType } from 'react';
import { isTrue } from '../expr';
import { useSnapshot } from '../store';
import type { UiNode } from '../types';
import { ButtonView, LinkView, ModalToggleView } from './actions';
import {
  CheckboxField,
  ColorField,
  DateField,
  DateRangeField,
  InputField,
  NumberRangeField,
  PasswordField,
  RadioField,
  RangeField,
  SegmentedField,
  SelectField,
  SwitchField,
  TextAreaField,
} from './fields';
import { AlertView, BadgeView, GridView, LegendView, MetricsView, ModalView, PersonaView, SectionView, TableView, TabsView, TextView } from './layout';
import { KeyValueField, PermissionsField, RepeaterField } from './structured';

/**
 * Node type → component. The PHP side never sends HTML, only these types.
 * A custom component is registered here under the type its PHP class returns.
 */
const REGISTRY: Record<string, ComponentType<{ node: any; compact?: boolean }>> = {
  section: SectionView,
  grid: GridView,
  tabs: TabsView,
  modal: ModalView,
  table: TableView,
  legend: LegendView,
  metrics: MetricsView,
  alert: AlertView,
  persona: PersonaView,
  text: TextView,
  badge: BadgeView,
  input: InputField,
  password: PasswordField,
  textarea: TextAreaField,
  select: SelectField,
  switch: SwitchField,
  checkbox: CheckboxField,
  radio: RadioField,
  segmented: SegmentedField,
  date: DateField,
  daterange: DateRangeField,
  range: RangeField,
  numberrange: NumberRangeField,
  color: ColorField,
  repeater: RepeaterField,
  keyvalue: KeyValueField,
  permissions: PermissionsField,
  button: ({ node, compact }) => <ButtonView node={node} size={compact ? 'sm' : 'md'} />,
  link: ({ node, compact }) => <LinkView node={node} size={compact ? 'sm' : 'md'} />,
  modaltoggle: ModalToggleView,
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

  return <Component node={node} compact={compact} />;
}

export function NodeList({ nodes }: { nodes: UiNode[] }) {
  return nodes.map((node) => <NodeView key={node.id} node={node} />);
}
