const test = require('node:test');
const assert = require('node:assert/strict');
const closeIncluded = require('./close-included.cjs');

// A fake GitHub: open PRs, and the compare status of each head against the merge.
function fake(prs, status, fail = new Set()) {
  const calls = { comments: [], closed: [] };
  const github = {
    paginate: async (fn, args) => (await fn(args)).data,
    rest: {
      pulls: {
        list: async () => ({ data: prs }),
        update: async ({ pull_number, state }) => { assert.equal(state, 'closed'); calls.closed.push(pull_number); },
      },
      repos: {
        compareCommitsWithBasehead: async ({ basehead }) => {
          const [base, head] = basehead.split('...');
          assert.equal(base, 'm3rge');
          if (fail.has(head)) throw new Error('Not Found');
          return { data: { status: status[head] } };
        },
      },
      issues: { createComment: async ({ issue_number, body }) => calls.comments.push([issue_number, body]) },
    },
  };
  return { github, calls };
}
const pr = (number, head, base = 'develop', ref = `b${number}`) => ({ number, head: { sha: head, ref }, base: { ref: base } });
const ctx = (merged = true) => ({ repo: { owner: 'o', repo: 'r' }, payload: { pull_request: { number: 156, merged, merge_commit_sha: 'm3rge' } } });

test('closes every open PR whose commits are in the merge, with a comment', async () => {
  const { github, calls } = fake([pr(155, 'a'), pr(154, 'b'), pr(140, 'c')], { a: 'behind', b: 'identical', c: 'behind' });
  assert.deepEqual(await closeIncluded({ github, context: ctx() }), [155, 154, 140]);
  assert.deepEqual(calls.comments, [[155, 'Included in #156'], [154, 'Included in #156'], [140, 'Included in #156']]);
  assert.deepEqual(calls.closed, [155, 154, 140]);
});

test('leaves PRs with their own commits open', async () => {
  const { github, calls } = fake([pr(160, 'x'), pr(161, 'y')], { x: 'ahead', y: 'diverged' });
  assert.deepEqual(await closeIncluded({ github, context: ctx() }), []);
  assert.deepEqual(calls.closed, []);
  assert.deepEqual(calls.comments, []);
});

test('never closes a release PR into main, a PR from develop or main, or the merged PR itself', async () => {
  const { github, calls } = fake(
    [pr(156, 's'), pr(170, 'd', 'main', 'develop'), pr(171, 'e', 'main', 'hotfix/x'), pr(172, 'f', 'develop', 'main'), pr(173, 'g', 'develop', 'develop')],
    { s: 'identical', d: 'identical', e: 'behind', f: 'behind', g: 'identical' },
  );
  assert.deepEqual(await closeIncluded({ github, context: ctx() }), []);
  assert.deepEqual(calls.closed, []);
});

test('does nothing when the PR was closed without merging', async () => {
  const { github, calls } = fake([pr(155, 'a')], { a: 'behind' });
  assert.deepEqual(await closeIncluded({ github, context: ctx(false) }), []);
  assert.deepEqual(calls.closed, []);
});

test('a PR that cannot be compared (e.g. a deleted fork) stays open and the rest still close', async () => {
  const { github, calls } = fake([pr(150, 'gone'), pr(151, 'a')], { a: 'behind' }, new Set(['gone']));
  assert.deepEqual(await closeIncluded({ github, context: ctx() }), [151]);
  assert.deepEqual(calls.closed, [151]);
});
