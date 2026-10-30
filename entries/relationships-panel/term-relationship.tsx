import { useMemo } from 'react';
import { TermSelector } from '@alleyinteractive/block-editor-tools';
import { Spinner } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

import type { RelationshipConfig } from './types';

type TermRecord = { id: number; name: string };

type TermRelationshipProps = {
  config: RelationshipConfig;
  ids: number[];
  onChange: (ids: number[]) => void;
};

export default function TermRelationship({ config, ids, onChange }: TermRelationshipProps) {
  const { label, multiple, subtype } = config;
  const idsKey = ids.join(',');

  const records = useSelect(
    (select) => {
      if (ids.length === 0) {
        return [];
      }

      return (select(coreStore).getEntityRecords('taxonomy', subtype, {
        include: ids,
        per_page: Math.min(ids.length, 100),
        context: 'view',
      }) ?? null) as TermRecord[] | null;
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [idsKey, subtype],
  );

  const selected = useMemo(
    () => ids.map((id) => ({
      id,
      title: records?.find((record) => record.id === id)?.name ?? `#${id}`,
    })),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [idsKey, records],
  );

  // TermSelector only reads `selected` on mount, so wait for the term names first.
  if (records === null) {
    return <Spinner />;
  }

  return (
    <TermSelector
      key={idsKey}
      label={label}
      multiple={multiple}
      onSelect={(terms) => onChange(terms.map((term) => term.id).filter((id) => id > 0))}
      selected={selected}
      subTypes={[subtype]}
    />
  );
}
