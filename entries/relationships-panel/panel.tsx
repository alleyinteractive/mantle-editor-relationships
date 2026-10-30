import { useMemo, useState } from 'react';
import { Selector } from '@alleyinteractive/block-editor-tools';
import { Notice } from '@wordpress/components';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { __, sprintf } from '@wordpress/i18n';

import type { RelationshipConfig, RelationshipValues } from './types';
import { fromIds, setRelationship, toIds } from './value';

type SelectedItem = { id: number; title: string };

type EntityRecord = {
  id: number;
  name?: string;
  title?: { raw?: string; rendered?: string };
};

type PanelProps = {
  config: RelationshipConfig;
  postType: string;
};

export default function RelationshipPanel({ config, postType }: PanelProps) {
  const {
    name, label, kind, subtype, multiple, max,
  } = config;
  const [values, setValues] = useEntityProp('postType', postType, 'mantle_relationships') as [
    RelationshipValues | undefined,
    (next: RelationshipValues) => void,
    unknown,
  ];
  const [overLimit, setOverLimit] = useState(false);

  const ids = toIds(values?.[name]);
  const idsKey = ids.join(',');

  const records = useSelect(
    (select) => {
      if (ids.length === 0) {
        return [];
      }

      return (select(coreStore).getEntityRecords(
        kind === 'post' ? 'postType' : 'taxonomy',
        subtype,
        { include: ids, per_page: ids.length, context: 'view' },
      ) ?? null) as EntityRecord[] | null;
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [idsKey, kind, subtype],
  );

  // Selector mirrors this prop into state, so it must keep the same identity between renders.
  const selected = useMemo<SelectedItem[]>(
    () => ids.map((id) => {
      const record = records?.find((item) => item.id === id);
      const title = record?.title?.rendered ?? record?.name ?? '';

      return { id, title: title || `#${id}` };
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [idsKey, records],
  );

  const onSelect = (items: SelectedItem[]) => {
    const next = items.map((item) => item.id);

    if (max !== null && next.length > max) {
      setOverLimit(true);
      return;
    }

    setOverLimit(false);
    setValues(setRelationship(values, name, fromIds(next, config)));
  };

  return (
    <PluginDocumentSettingPanel
      name={`mantle-relationship-${name}`}
      title={label}
      className="mantle-editor-relationships__panel"
    >
      <Selector
        // Remount to discard a rejected selection the Selector already holds internally.
        key={`${idsKey}-${overLimit ? 'over' : 'ok'}`}
        type={kind}
        subTypes={[subtype]}
        multiple={multiple}
        selected={selected}
        onSelect={onSelect}
        label={label}
        placeholder={kind === 'post'
          ? __('Search posts…', 'mantle-editor-relationships')
          : __('Search terms…', 'mantle-editor-relationships')}
        emptyLabel={__('No results found.', 'mantle-editor-relationships')}
        threshold={2}
        maxPages={1}
        className="mantle-editor-relationships__selector"
      />
      {overLimit && max !== null ? (
        <Notice status="warning" isDismissible={false}>
          {sprintf(
            // translators: %d: maximum number of related items.
            __('You can select up to %d items.', 'mantle-editor-relationships'),
            max,
          )}
        </Notice>
      ) : null}
    </PluginDocumentSettingPanel>
  );
}
