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

## The three permitted fields, and what each one is for

Frontmatter carries **no required keys**. Three are permitted, and each earns its place by holding a
fact neither the folder, nor git, nor the card's own shape can supply. Write the reason into the
value on all three: a key whose value is `yes` tells the next reader that somebody decided something
and not what.

| Key | Holds | Effect |
|---|---|---|
| `needs:` | prerequisite cards, as numbers | orders the lane, and shows what is stuck behind what |
| `waiting_on:` | who or what is being waited for, with a recheck date | surfaces as drift when the date arrives; keeps the unattended loop off the card |
| `not_for_the_loop:` | why this card is a person's | keeps the **unattended** loop off it, and nothing else |

```yaml
---
needs: 0057, 0082
waiting_on: the broker quote - recheck 2026-09-01
not_for_the_loop: edits the scheduled task that would be running it
---
```

`needs:` is what a card cannot start without, resolved within the project only, because card numbers
are per board. **It is read in both directions** - what this card waits on, and what waits on it -
and it is also the work order: `0002` naming `0004` is `0002` saying `0004` happens first, so the
lane lists `0004` ahead of it whatever the numbers are. There is no rank and no `priority:`, because
the ordering statement is already on the card and it decays on its own the moment the prerequisite
lands.

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

`## What I need from you`, directly under the title. Lead with the action. Put the reasoning
underneath, where it cannot stand between the reader and the ask.

### The shape

**The ask comes first: imperative, numbered, above everything else.** Somebody should know what they
are being asked to do within three lines of opening the card. One ask is one line. Two asks are
numbered. More than three asks is usually two cards.

Underneath it, whichever of these the card actually needs:

| Field | When |
|---|---|
| **Pass** | Always. What good looks like. Two or more conditions is a bulleted list, never one sentence joined by commas. |
| **Fail** | Always. What bad looks like, and what to do when it happens. |
| **Why it needs you** | Always. The part no lookup settles: a cost, a liability, a preference, a judgement. |
| **What's wrong** | Defect cards. The symptom as the reader would see it, not the design behind it. |
| **Cause** | Defect cards. The root cause, stated once. |

### Worked example

```markdown
## What I need from you

**Two answers.**

1. Can the loop keep committing unattended?  Yes / no.
2. Should "not for the loop" be a card convention, or stay a scheduler flag?

---

**On 1.** It has run twice on its own, 0011 and 0015, and both passed. Nothing to
run unless you want to watch one:

    .\bin\work-card.ps1 -Limit 1

Pass is all three of:
- the card ends in `ai-review/`, or stays in `in-progress/` with its unmet
  criteria still unticked
- `git log` shows three commits and no others
- the suite is green

Fail is anything committed you would not have. Say so in this card. `git revert`
undoes it cleanly, because each card and each move is its own commit.

**On 2.** Two cards need skipping now. 0008 writes boards into twenty five other
repos, and 0031 edits the script that would be running it. Nothing in the repo
settles where that fact belongs, which is why it is yours.
```

### How to write it

**Explain it like the reader is five.** This is the rule the others serve. They know their own
business, not our docs, so lead with the real-world thing they would recognise and name ours second:
"the emails your staff send about a job" before `InteractionEvent`, "the list of things the client
said they wanted" before "the anchor coverage table". Unpack every internal name on first use, in the
same clause, never as a glossary link. The commonest failure here is not a card that is wrong. It is
a card that is perfectly accurate and completely opaque, and it costs a whole round trip while the
reader asks what it is actually about.

**Cut everything that does not change the answer.** This is the one that shortens cards, and it is a
test rather than a preference: take any paragraph out and ask whether the reader would now answer
differently. If not, it was not context, it was throat-clearing - and that is true of writing which is
accurate, interesting and hard-won. Background nobody acts on, the third piece of evidence for a point
already made, the history of how the card was drafted, the aside that shows the work: all of it costs
the reader attention and buys them nothing. **An agent writing a card is the usual source**, because
producing more text is cheap for it and reading the text is the entire cost to the person.

**Say how the situation arose, in one or two sentences.** "Nobody ever decided to leave email out, it
just never got ticked" is the sentence that makes a card land, and it passes the test above because a
reader who cannot see how a thing happened cannot judge whether it matters. Its own paragraph, not
its own section: this is the commonest place a card starts growing a history of itself.

**Cost the options in plain words.** "Costs the most, and nobody has researched how yet" beats "a
research pass plus a connector, in a version that already contains the assessment engine". The
second is more precise and tells the reader less.

**Put the answer in the recommendation, ready to paste.** End `## Recommendation` with the exact
line to copy into `## Decided`, dated, written as the reader would write it. Answering is then a
copy rather than a composition, which is most of the difference between a card answered today and
one answered next month.

These five govern the whole card, not only the ask. `## Why`, the options and the tasks are read by
the same person in the same sitting.

**A whole card fits in 100 lines.** Measured over the 108 cards in `human-review/` across 22 boards
on 2026-08-15: the median is 63 lines and nine in ten are under 104, so this is the estate's own
habit written down rather than a new constraint - but the tail is where the reader is lost, at 301,
194, 173 and 161 lines. Over the budget means one of two things and never a third: it is two cards,
or it failed the cut-everything test above. **It is not a licence to compress.** Cutting a sentence
the answer depends on to make a number is the one way to fail this rule while passing it, and the
ask, the pass condition and the costs are the last things that may go.

**Checkable, not merely short.** "Four clicks in a browser" is not an ask: it says how much work it
is, not what the work is. Name the exact thing to do and what a pass looks like. Numbered steps each
get their own expected result, so a failure points at one step rather than at the whole card.

**Write to the reader, not about them.** "Most of what waits for you", not "a large share of what
waits on Rob". The card is addressed to a person, so address them.

**One idea per sentence.** A sentence needing both a colon and a semicolon to hold itself together is
a list. Make it a list.

**Do not write for the quote.** "Ceremony carrying no information" is a good line and a bad
instruction. The reader wants to know what to do, not to be persuaded that the card is clever.

**If the answer is "look at it", give the link.** The board derives a project's local URL from its
directory name, so a card that wants a page checked should name that page rather than describe it.

`## Why` is not this section and must not be doing its job. "Why this card exists" and "why this card
stopped and needs a person" are different questions, and only the second one is the reader's problem.

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

**`## Direction` may be pruned, but only intentionally, and needing to is a defect report about
whatever filled it.** The target state is one where pruning is never needed. A card that has to be
pruned is a card something has been writing to without having anything new to say: one real card
reached 4,764 lines across 100 dated entries, most of them recording that it was still blocked on
the same ruling as the entry above, until it was too large for the agent file reader that had to
open it. So prune when a person decides to, never as routine tidying, and treat the decision as
evidence that the writer upstream needs fixing rather than the log needs trimming. An entry that
records nothing a reader could act on should not have been written.

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
