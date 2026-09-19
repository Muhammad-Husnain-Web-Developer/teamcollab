import { describe, it, expect } from 'vitest';
import { filterMentionCandidates } from './mentions';

const members = [
  { id: 1, name: 'Zed Alpha',     display_name: null,    email: 'zed@example.test' },
  { id: 2, name: 'Alice Peer',    display_name: 'Ali',   email: 'alice@example.test' },
  { id: 3, name: 'Bob Peer',      display_name: null,    email: 'bob@example.test' },
  { id: 4, name: 'Malik Hussain', display_name: null,    email: 'malik@example.test' },
  { id: 5, name: 'Carol',         display_name: 'Caz',   email: 'carol@example.test' },
];

const ids = (list) => list.map(m => m.id);

describe('filterMentionCandidates', () => {
  it('lists everyone alphabetically for an empty query', () => {
    expect(ids(filterMentionCandidates(members, ''))).toEqual([2, 3, 5, 4, 1]);
  });

  it('ranks prefix matches (including on a later word) ahead of substring matches', () => {
    // "al": Alice (name prefix), Ali (display prefix), Zed Alpha (word prefix) → before Malik (substring only)
    expect(ids(filterMentionCandidates(members, 'al'))).toEqual([2, 1, 4]);
  });

  it('matches the display name and email too, case-insensitively', () => {
    expect(ids(filterMentionCandidates(members, 'CAZ'))).toEqual([5]);
    expect(ids(filterMentionCandidates(members, 'bob@'))).toEqual([3]);
  });

  it('respects the limit and tolerates a missing member list', () => {
    expect(filterMentionCandidates(members, '', 2)).toHaveLength(2);
    expect(filterMentionCandidates(undefined, 'a')).toEqual([]);
    expect(filterMentionCandidates(null, '')).toEqual([]);
  });

  it('returns nothing for a query nobody matches', () => {
    expect(filterMentionCandidates(members, 'xyz')).toEqual([]);
  });
});
