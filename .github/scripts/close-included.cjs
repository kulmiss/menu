// After a pull request is merged into develop, close every other open pull
// request whose commits are all in that merge ("Included in #X"). With stacked
// PRs the top one points at develop and holds the ones below it, so merging it
// closes the rest (owner, 2026-11-18). Nothing is deleted: no branch, no
// commit; develop and main are never touched.

const KEEP = new Set(['develop', 'main']);

/**
 * @param {{ github: any, context: any, core?: any }} args
 * @returns {Promise<number[]>} the pull requests it closed
 */
module.exports = async function closeIncluded({ github, context, core }) {
  const merged = context.payload.pull_request;
  if (!merged || !merged.merged || !merged.merge_commit_sha) return [];
  const { owner, repo } = context.repo;
  const log = (m) => (core ? core.info(m) : undefined);

  const open = await github.paginate(github.rest.pulls.list, { owner, repo, state: 'open', per_page: 100 });
  const closed = [];
  for (const pr of open) {
    if (pr.number === merged.number) continue;
    // A release PR (into main) or a PR from develop/main itself is never closed here.
    if (pr.base.ref === 'main' || KEEP.has(pr.head.ref)) continue;
    let status;
    try {
      const { data } = await github.rest.repos.compareCommitsWithBasehead({ owner, repo, basehead: `${merged.merge_commit_sha}...${pr.head.sha}` });
      status = data.status;
    } catch (e) {
      log(`#${pr.number}: could not compare (${e.message}); left open`);
      continue;
    }
    // "behind" or "identical": the PR has no commit the merge doesn't already have.
    if (status !== 'behind' && status !== 'identical') {
      log(`#${pr.number}: has its own commits (${status}); left open`);
      continue;
    }
    await github.rest.issues.createComment({ owner, repo, issue_number: pr.number, body: `Included in #${merged.number}` });
    await github.rest.pulls.update({ owner, repo, pull_number: pr.number, state: 'closed' });
    log(`#${pr.number}: included in #${merged.number}; closed`);
    closed.push(pr.number);
  }
  return closed;
};
