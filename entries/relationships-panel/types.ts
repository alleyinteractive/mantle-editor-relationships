export type RelationshipConfig = {
  name: string;
  label: string;
  kind: 'post' | 'term';
  subtype: string;
  multiple: boolean;
  max: number | null;
};

export type RelationshipValue = number | number[] | null;

export type RelationshipValues = Record<string, RelationshipValue>;

export type EditorRelationships = {
  postType: string;
  relationships: RelationshipConfig[];
};

declare global {
  interface Window {
    mantleEditorRelationships?: EditorRelationships;
  }
}
