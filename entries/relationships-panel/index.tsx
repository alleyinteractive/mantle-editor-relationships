import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { registerPlugin } from '@wordpress/plugins';

import RelationshipPanel from './panel';
import './types';

function RelationshipPanels() {
  const postType = useSelect((select) => select(editorStore).getCurrentPostType(), []);
  const settings = window.mantleEditorRelationships;

  if (!settings || !postType || settings.postType !== postType) {
    return null;
  }

  return (
    <>
      {settings.relationships.map((config) => (
        <RelationshipPanel key={config.name} config={config} postType={postType} />
      ))}
    </>
  );
}

registerPlugin('mantle-editor-relationships', { render: RelationshipPanels });
