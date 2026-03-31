# CLAUDE

This file is a Claude Code addendum to [`AGENTS.md`](AGENTS.md). All rules in `AGENTS.md` apply. This file only documents genuine Claude-specific differences or clarifications.

## Branch Naming
New work branches follow `claude/<task>` (not `codex/<task>`).

## Terminal Ownership
Claude drives Sail, git, and artisan commands—assume the user does not have a shell open unless they say otherwise. This is the same ownership model as described in `AGENTS.md` for Codex.

## Tool Preferences
Claude Code has native file tools that should be used instead of shell equivalents:
- Read files → `Read` tool, not `cat`/`head`/`tail`
- Edit files → `Edit` tool, not `sed`/`awk`
- Create files → `Write` tool, not heredoc redirects
- Find files → `Glob` tool, not `find`/`ls`
- Search content → `Grep` tool, not `grep`/`rg`
- Reserve `Bash` for commands that genuinely require shell execution (Sail, git, artisan, npm).

## Subagents
Claude Code supports specialized subagents via the `Agent` tool:
- `Explore` — fast, read-only codebase exploration and search; use instead of multiple manual Grep/Glob rounds
- `Plan` — architecture and implementation planning before writing code
- Follow the multi-agent coordination rules in `AGENTS.md` (path-scoped writes, no overlapping write scopes, handoff notes).

## Persistent Memory
Claude has a file-based memory system at `/root/.claude/projects/-opt-btc-invoice/memory/`. Use it to preserve non-obvious project context, user preferences, and feedback across sessions so they do not have to be re-established each time. Do not store things already derivable from the code, git history, or `docs/`.

## Confirmation Defaults
Claude's built-in defaults for destructive or externally visible actions (push, merge, force ops, dropping data) are conservative. The `AGENTS.md` rules on doc sync before push, clean tree before merge, and explicit confirmation before destructive steps already align with this—follow them as stated.
