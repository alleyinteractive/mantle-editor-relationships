import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel, store as editorStore } from '@wordpress/editor';

import PostRelationship from './post-relationship';
import TermRelationship from './term-relationship';
import type { RelationshipConfig, RelationshipValues } from './types';
import { fromIds, setRelationship, toIds } from './value';

type PanelProps = {
  config: RelationshipConfig;
  postType: string;
};

export default function RelationshipPanel({ config, postType }: PanelProps) {
  const { name, label, kind } = config;
  const [values, setValues] = useEntityProp('postType', postType, 'mantle_relationships') as [
    RelationshipValues | undefined,
    (next: RelationshipValues) => void,
    unknown,
  ];
  const currentPostId = useSelect(
    (select) => select(editorStore).getCurrentPostId(),
    [],
  ) as number;

  const ids = toIds(values?.[name]);
  const onChange = (next: number[]) => {
    setValues(setRelationship(values, name, fromIds(next, config)));
  };

  return (
    <PluginDocumentSettingPanel
      name={`mantle-relationship-${name}`}
      title={label}
      className="mantle-editor-relationships__panel"
    >
      {kind === 'post' ? (
        <PostRelationship
          config={config}
          currentPostId={currentPostId}
          ids={ids}
          onChange={onChange}
        />
      ) : (
        <TermRelationship config={config} ids={ids} onChange={onChange} />
      )}
    </PluginDocumentSettingPanel>
  );
}
