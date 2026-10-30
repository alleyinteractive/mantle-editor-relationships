import { PostPicker } from '@alleyinteractive/block-editor-tools';
import { __, sprintf } from '@wordpress/i18n';

import type { RelationshipConfig } from './types';

type PostRelationshipProps = {
  config: RelationshipConfig;
  currentPostId: number;
  ids: number[];
  onChange: (ids: number[]) => void;
};

export default function PostRelationship({
  config,
  currentPostId,
  ids,
  onChange,
}: PostRelationshipProps) {
  const { multiple, max, subtype } = config;
  const getPostType = () => subtype;
  const suppressPostIds = [currentPostId, ...ids];

  if (!multiple) {
    return (
      <PostPicker
        allowedTypes={[subtype]}
        getPostType={getPostType}
        modalFormat="list"
        onReset={() => onChange([])}
        onUpdate={(id) => onChange([id])}
        resetText={__('Remove', 'mantle-editor-relationships')}
        suppressPostIds={suppressPostIds}
        value={ids[0] ?? 0}
      />
    );
  }

  const canAdd = max === null || ids.length < max;

  return (
    <div className="mantle-editor-relationships__posts">
      {ids.map((id, index) => (
        <PostPicker
          key={id}
          allowedTypes={[subtype]}
          getPostType={getPostType}
          modalFormat="list"
          onReset={() => onChange(ids.filter((_, i) => i !== index))}
          onUpdate={(next) => onChange(ids.map((current, i) => (i === index ? next : current)))}
          resetText={__('Remove', 'mantle-editor-relationships')}
          suppressPostIds={suppressPostIds}
          value={id}
        />
      ))}
      {canAdd ? (
        <PostPicker
          allowedTypes={[subtype]}
          getPostType={getPostType}
          modalFormat="list"
          onReset={() => {}}
          onUpdate={(id) => onChange([...ids, id])}
          selectText={ids.length > 0
            ? __('Add another', 'mantle-editor-relationships')
            : __('Add', 'mantle-editor-relationships')}
          suppressPostIds={suppressPostIds}
          value={0}
        />
      ) : (
        <p className="mantle-editor-relationships__limit">
          {sprintf(
            // translators: %d: maximum number of related items.
            __('Maximum of %d reached.', 'mantle-editor-relationships'),
            max,
          )}
        </p>
      )}
    </div>
  );
}
