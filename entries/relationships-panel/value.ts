import type { RelationshipConfig, RelationshipValue, RelationshipValues } from './types';

export function toIds(value: RelationshipValue | undefined): number[] {
  if (value === null || value === undefined) {
    return [];
  }

  return Array.isArray(value) ? value : [value];
}

export function fromIds(ids: number[], config: RelationshipConfig): RelationshipValue {
  if (!config.multiple) {
    return ids[0] ?? null;
  }

  return [...new Set(ids)];
}

export function setRelationship(
  values: RelationshipValues | undefined,
  name: string,
  next: RelationshipValue,
): RelationshipValues {
  return { ...(values ?? {}), [name]: next };
}
