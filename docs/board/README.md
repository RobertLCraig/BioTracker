# The board: one task per file, and the folder is the state

**A card is a file. The folder it sits in is its state. Moving it is `git mv`.**

```
docs/board/
  todo/           ready to pick up, nothing in the way
  in-progress/    an agent is building it right now
  ai-review/      built, awaiting an adversarial review by an agent
  human-review/   a person owes something: a call to make, a build to accept,
                  or an outside party to chase
  done/           reviewed, accepted, delivered. The final product.
  discarded/      abandoned or superseded, with one line of why
```

The pipeline is a loop, not a line. Work leaving `human-review` goes back to an agent and returns
either to `human-review` again or forward to `ai-review`. **Nothing reaches `done/` without an
adversarial review**, which is what `ai-review/` is for: the point is not that the work exists, it
is that somebody tried to break it first.

**A decision card skips `ai-review/`.** There is no artefact to review; its exit is the decision
itself, so it goes from `human-review/` straight to `done/`.

There is no `status:` field, because the folder already says it. A card carrying both would
eventually disagree with itself, which is the failure this board exists to remove. There is no
`created`, `updated` or `author` field either: git holds those, and a copy would drift.

## The one field that is not derivable: `needs:`

```yaml
---
needs: 0057, 0082
---
```

What a card cannot start without, as card numbers. It is the only dependency notation there is, and
it earns its place because it is the one fact neither the folder nor git nor the card's own shape
can supply.

**It has to be in the frontmatter, not in a paragraph.** A blocker named only in prose is one no
view can show, and one real board ran for weeks with most of its dependencies sitting in sentences
like "answer 0025 first". A board that renders `needs:` shows the part that is **still open**,
treating `done/`, `discarded/` (answered by dropping it) and `ai-review/` (built, with only its
acceptance pending) as settled, so a card whose blockers have all landed stops showing as blocked
without anybody editing it.

**Declare a blocker, not an influence.** If the card can be built now and a later answer merely
refines it, that belongs in `## Plan` with the interim stated ("build to a named constant, the swap
is one call site"), because a `needs:` that is not really a blocker makes a ready card look stuck,
which is the same failure as a holding lane nobody owns.

## One lane for the human, not three

A call to make, a build to accept, and an outside party to chase are the same state: nothing moves
until a person spends attention. Splitting them would be three folders to sweep instead of one, and
what a card wants is already derivable from its own shape, so the board can say "8 to call, 3 to
accept" without a second folder saying it.

The lane is named after the job, not the person, because these boards are read by more than their
author and the convention is the same on every project.

There is no `blocked/` or `waiting/` lane. Both named a state without naming who clears it, and a
holding lane nobody owns is how one real board reached 21 cards nobody could clear. If an outside
party owes you something, chasing them is your action: the card sits in `human-review/` with a
`waiting_on:` note carrying a recheck date, and a past-due recheck is surfaced.

## The one section a card in `human-review/` must have

```markdown
## What I need from you
Which of the three, because it decides the storage shape and four cards are queued behind it.
An agent cannot settle it: the options differ on cost and liability, not on evidence.

<http://retireforecast.test/scenarios/43>
```

One or two sentences, directly under the title, saying **why this needs a person**: what it decides,
what it blocks, and why an agent could not settle it from the docs, the standards and its own
research.

**It has to be checkable, not merely short.** "Four clicks in a browser" is not an ask: it says how
much work it is and not what the work is. Name the exact thing to do, what a pass looks like, and
what a failure means. If there are several steps, number them and give each one its own expected
result, so a failure points at one cause instead of at the whole card. The test is whether somebody
could act on it without opening anything else. `## Why` was doing this job and doing it badly, because "why this card exists" and "why
this card stopped" are different questions and only the second one is the reader's problem.

**If the answer is "look at it", give the link.** The board derives a project's local URL from its
directory name, so a card that wants a page checked should name that page rather than describe it.

A card in `human-review/` without this section is flagged as not ready, the same way a feature with
no acceptance criteria is. Making the gap loud is the only enforcement there is.

## Answering a card moves it

Recording an answer is the review, so the card leaves `human-review/` on the way out and lands back
in `todo/`. Not `done/`: an answer is almost always the start of work rather than the end of it, and
an agent picking it up can move it on if there is nothing to do. Direction is not an answer and
moves nothing, because steering a card is something you do to work that is still yours to steer.

## Two kinds of card, and the kind is derived

A card with `## Options` is a **decision**. A card with `## Tasks` is a **feature**. Nothing
declares its kind, because a declared kind is one more thing that can disagree with the card's own
contents.

**Decision:** `# title`, `## Why`, `## Options` (a **numbered** list, at least two, each with its cost),
`## Recommendation`, `## Decided`. The recommendation is the point: a decision surfaced without one
hands over the whole problem, while one that recommends has done the reading and leaves only the
judgement.

**Feature:** `# title`, `## Why`, `## Not this card`, `## Acceptance`, `## Tasks`, and an optional
`## Plan` that is deleted when the card reaches `done/`. `## Not this card` is a scope fence the
agent reads and obeys, and it is the cheapest defence against the commonest agent failure, which
is quietly building three adjacent things.

Acceptance uses EARS phrasing (`WHEN <trigger>, THE APP SHALL <observable result>`) inside
`<!-- AC:BEGIN -->` sentinels, so it stays greppable and interoperable with Backlog.md's
convention.

## Direction and Decided

`## Direction` is steering: how to approach something, a spike worth running first, a constraint
the agent should know. `## Decided` is the answer to a decision card, and filling it is that card's
exit condition. Both are append-only dated entries, added and never edited. An answer recorded as
direction neither reads as a ruling nor moves the card, which was found by watching it happen.

An entry can carry screenshots. They live in `docs/board/attachments/`, named after the card and
dated, and a card links to one as `![name](../attachments/NNNN-YYYY-MM-DD-N.png)`. That path is
correct from every lane folder and therefore survives every move, where a picture stored beside the
card would be orphaned by the first `git mv`. It is an ordinary relative link, so the card renders
with its screenshots in any markdown viewer with nothing running, and the attachments are committed
with the card that references them rather than left behind untracked.

## Naming

`NNNN-slug.md`, four digits, allocated in order and **never reused**, so a number stays quotable
after the card has moved three times. Cross-project identity is derived at render time from the
directory name, giving `progressboard#0007`. Nothing on disk carries a project prefix.

## What the board is not

It is not the specification, the rationale, or the narrative. What the system is lives in the PRD
and the design proposal; why we decided something lives with the decided cards; what happened is
the commit log. A card links to those. It does not restate them.
