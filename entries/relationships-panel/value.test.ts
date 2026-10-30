import { describe, expect, it } from '@jest/globals';
import { fromIds, setRelationship, toIds } from './value';
import type { RelationshipConfig } from './types';

const single: RelationshipConfig = {
  name: 'featured_page',
  label: 'Featured Page',
  kind: 'post',
  subtype: 'page',
  multiple: false,
  max: null,
};

const multiple: RelationshipConfig = { ...single, name: 'related_pages', multiple: true };

describe('toIds', () => {
  it('normalizes empty, single, and list values', () => {
    expect(toIds(null)).toEqual([]);
    expect(toIds(undefined)).toEqual([]);
    expect(toIds(5)).toEqual([5]);
    expect(toIds([3, 4])).toEqual([3, 4]);
  });
});

describe('fromIds', () => {
  it('returns the first ID or null for single relationships', () => {
    expect(fromIds([], single)).toBeNull();
    expect(fromIds([3, 4], single)).toBe(3);
  });

  it('returns a de-duplicated list for multiple relationships', () => {
    expect(fromIds([1, 1, 2, 3], multiple)).toEqual([1, 2, 3]);
    expect(fromIds([], multiple)).toEqual([]);
  });
});

describe('setRelationship', () => {
  it('sets a value without mutating the input', () => {
    const values = { a: 1 };

    expect(setRelationship(values, 'b', [2])).toEqual({ a: 1, b: [2] });
    expect(values).toEqual({ a: 1 });
    expect(setRelationship(undefined, 'a', null)).toEqual({ a: null });
  });
});
