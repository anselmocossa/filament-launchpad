# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 1.16.x  | Yes, fixes and security advisories |
| 1.15.x  | Security fixes only |
| < 1.15  | No |

Fixes land on the latest minor release. Please upgrade before reporting something you found on an older version.

## Reporting a vulnerability

Please do not open a public issue, discussion or pull request for a security problem.

Report it in one of two ways:

1. **GitHub private vulnerability reporting (preferred):** open
   [a draft advisory](https://github.com/anselmocossa/filament-launchpad/security/advisories/new).
   Only the maintainer sees it, the fix is prepared in a private fork, and you are credited on the published advisory.
2. **E-mail:** anselmokossa.apk@gmail.com, with `[filament-launchpad]` in the subject.

Please include:

- the package version, plus the Filament, Laravel and PHP versions;
- what an attacker gains (sees a space, page or tile they may not see, reads a figure computed without their scopes, edits someone else's launchpad);
- the shortest steps that reproduce it, or a small proof of concept;
- the configuration that matters: whether Filament Shield permissions are on, which generators are enabled, and whether the launchpad is per user or global.

### What to expect

- **First reply:** within 5 working days.
- **Assessment and severity:** within 10 working days of that reply.
- **Fix:** a patch release for a confirmed high or critical issue as soon as it is ready, lower severities on the next minor release.
- **Credit:** your name on the advisory and in the changelog, unless you prefer to stay anonymous.

If you get no reply within 10 working days, please send the e-mail again; it is a single maintainer, not a team.

## Scope

This package turns the panel homepage into a launchpad of spaces, pages, sections and tiles. A tile is a shortcut and often a live figure, so the boundary is what each user may see on their own homepage and who may change it.

**In scope:**

- A space, page, section or tile visible to a user that Shield permissions, a `visible()` condition or the panel's own `canAccess()` should have hidden.
- A tile's figure (a count, a KPI, a total) computed without the viewer's own query scopes, tenant or permissions, so it reveals records they cannot open.
- A user reading or editing another user's launchpad, or changing the global launchpad configuration, without authorization.
- Stored XSS or HTML injection through the title, description, badge, icon or URL of a space, page, section or tile, including values a generator writes.
- Open redirect through a tile URL, or SSRF through a generator or a tile that fetches a URL server side.
- Navigation items or routes registered by the package that bypass the panel's middleware or authorization.

**Out of scope:**

- Layout, tile sizing, icon tone, colours, branding or translations.
- An administrator with permission to edit the launchpad pointing a tile wherever they like, or showing a figure they are entitled to see. That is the role working as intended.
- A tile that shows a stale figure because of your own cache settings.
- Findings against an unsupported version, or that depend on a deliberately insecure configuration of the host application.
- Vulnerabilities in Filament, Laravel or Filament Shield themselves. Please report those upstream.

## Hardening in your own panel

Not vulnerabilities, but worth checking in production:

- Turn on Shield permissions for the launchpad resources, so editing spaces and pages is not implicitly open to every panel user.
- Give each tile a `visible()` condition that matches the permission needed to open its destination, so a user never sees a shortcut to a page they cannot reach.
- Make sure a tile's query respects the model's global scopes and your tenant, not just the raw table.
- Review the generators you enable, since they write the titles and URLs that end up rendered on the homepage.
