<?php

namespace Modules\Core\Support\Automation;

use Closure;

class UpstreamReplayPlanner
{
    /**
     * @param Closure(array<int, string>): string $git
     */
    public function __construct(private readonly Closure $git)
    {
    }

    /**
     * @return array{
     *   track: string,
     *   upstream_ref: string,
     *   upstream_sha: string,
     *   patch_base_ref: string,
     *   patch_source_ref: string,
     *   patch_range: string,
     *   patch_commits: array<int, string>,
     *   patch_commit_count: int,
     *   replay_branch: string,
     *   pr_base_branch: string|null,
     *   verification_profile: string,
     *   open_pr: bool
     * }
     */
    public function plan(UpstreamTrackConfig $track): array
    {
        $upstreamRef = $track->upstreamRemoteRef();
        $upstreamSha = trim(($this->git)(['rev-parse', $upstreamRef]));
        $revList = trim(($this->git)(['rev-list', '--reverse', '--no-merges', $track->patchRange()]));
        $patchCommits = $revList === ''
            ? []
            : (preg_split('/\R+/', $revList) ?: []);

        return [
            'track' => $track->name,
            'upstream_ref' => $upstreamRef,
            'upstream_sha' => $upstreamSha,
            'patch_base_ref' => $track->patchBaseRef,
            'patch_source_ref' => $track->patchSourceRef,
            'patch_range' => $track->patchRange(),
            'patch_commits' => $patchCommits,
            'patch_commit_count' => count($patchCommits),
            'replay_branch' => $track->replayBranch,
            'pr_base_branch' => $track->prBaseBranch,
            'verification_profile' => $track->verificationProfile,
            'open_pr' => $track->openPr,
        ];
    }
}
