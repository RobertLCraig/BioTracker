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

### Where the card produced code, the adversarial pass includes security

**Correctness and security find different things** (Rob, 2026-08-20). Code can pass every test, match
the card's acceptance and still hand a stranger somebody else's data, so a review that only asks
whether the code is right has done half the job. It also asks how the code is attacked.

Three questions, and the answers go **on the card**, as a dated `## Comments` entry, before it leaves
`ai-review/`:

1. **Where is it weakest**, said the way somebody attacking it would use it, not as a category name.
2. **What is unchecked** on any path in: unvalidated input, an entry point with no permission check,
   a background job, a machine-facing interface. Not only the screen the card was about.
3. **What does it leak** when it fails: another tenant's data, an internal id, a stack trace, or the
   bare fact that a record exists.

**A pass that found nothing still gets written down.** It says what was looked at and why it holds.
"Looks fine" is not a review, because an unrecorded pass cannot be told apart from one that never
happened, and the next reader has no way to know which they are looking at.

A card that produced no code (a doc, a decision, a research pass) has nothing to attack and skips this.

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
| `no_outward_effect:` | why an outward-effect flag on this card is a false positive | silences that one flag, and grants nothing |

```yaml
---
needs: 0057, 0082
waiting_on: the broker quote - recheck 2026-09-01
not_for_the_loop: edits the scheduled task that would be running it
no_outward_effect: "published" here is a template version's state, not a deploy
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

**A card that has been answered is settled too, in whatever lane it is sitting in.** Answering moves
a card back to `todo/`, so an answered decision sits in a work lane for as long as the work takes,
and a lane test alone read it as an open blocker for ever: on one board, 17 answered cards in `todo/`
were freezing 8 others, and the unattended loop will not start a card whose `needs:` is unresolved.
The prerequisite decays on the thing it was actually waiting for, which for a decision is the answer
and not the folder. It says nothing about the answered card itself, which is still open work, still
in the queue and still counted.

**Declare a blocker, not an influence.** If the card can be built now and a later answer merely
refines it, that belongs in `## Plan` with the interim stated ("build to a named constant, the swap
is one call site"), because a `needs:` that is not really a blocker makes a ready card look stuck,
which is the same failure as a holding lane nobody owns.

### `not_for_the_loop:` is decided by the EFFECT, not by the difficulty

**Only code development and review run unattended** (Rob, 2026-08-20). If doing this card has an
effect that leaves the repository, it needs a person driving the session, and the card must say so.

Carry the key when the work involves any of:

- changing DNS, or anything else about how a domain resolves
- deploying, publishing or pushing anything to a live host
- sending a message somebody receives: mail, a push notification, a post
- creating, changing or paying for an account with a third party
- `git push` on a repository that has not been through the credential scrub
- anything a browser has to be driven through in order to confirm it

The test is not "is this hard" or "would an agent get it right". It is **can this be undone by
deleting a file.** A commit can. A sent email cannot, and a DNS record was serving traffic to real
people while it was wrong.

The value is the reason, in the reader's terms: `not_for_the_loop: publishes the site to Hostinger`.
A card that edits the very script that would be running it carries the key for a different reason,
and that is fine. The key means "not the unattended loop"; the value says why.

### A confirmed false positive is recorded, not argued with

The board flags a card whose `## Acceptance` uses one of six words (deploy, DNS, publish, push to
live, send, browser) and carries no `not_for_the_loop:`. That flag is a **candidate and not a
verdict**, because whether work reaches outside the repository turns on meaning, and a fair share of
its hits are the word rather than the effect: a template **version** being *published* is a database
row's lifecycle state, accepting a certificate **without** a *deploy* is the opposite of deploying,
*publishing* our own metadata page is serving our own route, and anybody *sending* a delete to a
route is an HTTP verb in a test.

When you have read one and it is a word rather than an effect, say so on the card:

```yaml
no_outward_effect: "published" is a template version's lifecycle state, not a deploy
```

Presence is the declaration and the value is the reason, exactly as with `not_for_the_loop:`. **It is
not that key and must not be used as it.** `no_outward_effect:` silences one writing flag and grants
nothing; the loop reads `not_for_the_loop:` and only that, so a card carrying this one is exactly as
available to an unattended session as it was before. Reaching for `not_for_the_loop:` to quieten a
false positive is the thing this exists to prevent: it strands a buildable card in the person's
queue for a word.

**A card that needs a person also needs a starting point**, so its `## Plan` matters more than most.
See `## Plan` is written for a stranger, below. A card nobody knows how to start is a card that does
not get started.

## Surface the whole chain of decisions, not the first link

**An option that only raises three more questions has not been costed** (Rob, 2026-08-20). The
reader answers, expects work to begin, and is asked again a day later. Two round trips is a card
written from the writer's side of the problem rather than the reader's.

So when an option would raise a further decision, say so on the option, in one clause: what would
have to be settled next, and roughly what it turns on. Where the follow-up is small enough to be
pre-empted, pre-empt it. Ask both questions on the one card, numbered, so a single answer settles
the chain.

This is not a licence to bundle unrelated questions. The test is whether answering question one
makes question two inevitable. If it does, they are one decision presented in two parts and belong
together. If it does not, they are two cards.

## Links: say what the relationship IS, never a bare card number

**A card number dropped into a sentence is not a link, it is a puzzle.** `firecrm#0053` in the
middle of a paragraph tells the reader that some other card matters and nothing about why, so they
open it to find out, and that is a page load spent on something one clause would have said. This is
the commonest way a card wastes the reader's time while looking complete.

So relationships go in a `## Links` section, under `## Why`, using the two relationship types every
issue tracker settled on:

```markdown
## Links

**Blocked by**
- `0088` - the time-capture phasing has to be ratified before this can be scoped.

**Relates to**
- `firecrm#0053` - the same scrub rule stopped that repo getting a remote, and the
  reasoning there applies here unchanged.
```

**One line each, and the line after the dash is the whole point.** It says why the reader is being
sent there. A link with no reason should not have been written; nothing is lost by deleting it,
because a card that genuinely matters can be described in a clause.

**Only the outgoing half is written.** `Blocks` and `Referenced by` are the same edges read
backwards, so the board derives and renders both, and a card that wrote them down would be keeping
a second copy that goes stale the moment the other card moves. This is the same rule as `status:`:
if the board can compute it, the card does not carry it.

**`Blocked by` has to agree with `needs:`.** The frontmatter is the machine-readable half and this
section is the human-readable half of one fact, so every number under `Blocked by` appears in
`needs:` and every number in `needs:` appears here with its reason. A card that writes one and not
the other has a blocker no view can show, or a reason no reader can find.

## Is this actually a person's to decide?

**Ask this before writing a decision card at all, because the scarcest thing on the board is the
reader's attention and the cheapest thing on it is an agent's opinion.** A decision card spends a
person; a card that did not need to exist spends them for nothing, and enough of those teach them to
stop reading the ones that did.

**A decision belongs to a person only when the answer turns on something no amount of reading can
settle.** In practice that is four things and nothing else:

| It is theirs when the answer turns on | Example |
|---|---|
| **A preference** | how much detail they want on a screen they will use every day |
| **A cost they carry** | money, hours, a support burden, a thing that gets harder to undo |
| **A risk or liability they own** | a benefits claim, a client relationship, data that must not leave the machine |
| **Local knowledge nobody wrote down** | what a client actually meant, what happened before the repo existed |

**If the answer is instead a matter of established practice, the agent researches it, applies it,
and says on the card what it applied and where the practice came from.** Which HTTP status a
validation failure returns, whether an id or a slug goes in a URL, how a table should be indexed,
what a date format should be: these have correct answers that a search settles in minutes, and
surfacing them as a decision is an agent asking a person to do its reading. **Say what you applied.**
A choice made silently is one nobody can overturn, so it goes in `## Comments` or `## Plan` with the
source, as a statement rather than a question.

**When it is genuinely both, split it.** Research the standard part, apply it, and put only the
residue in front of the person. "The industry default is X, applied. What I cannot settle is whether
your situation is the exception, because Y" is one line of reading instead of a whole card.

**This test is also what `human-review/` is for**, and the two are one rule stated twice on purpose:
the four rows above are the whole of what may enter that lane as a decision. See
`## human-review/ IS A DECISION QUEUE AND NOT AN APPROVAL GATE` below for the other two ways in, both
narrow, and for what the lane is explicitly NOT.

## One lane for the human, not three

A call to make, a decision to take, and an outside party to chase are the same state: nothing moves
until a person spends attention. Splitting them would be three folders to sweep instead of one, and
what a card wants is already derivable from its own shape, so the board can say "8 to call, 3 to
decide" without a second folder saying it.

The lane is named after the job, not the person, because these boards are read by more than their
author and the convention is the same on every project.

### `human-review/` IS A DECISION QUEUE AND NOT AN APPROVAL GATE

Rob, 2026-08-21, and it is the rule this whole lane turns on:

> Cards only move into human-review if I need to make a decision. It isn't there for me to approve
> or disapprove of any work done, it's just so that I can answer any questions that the agent
> raised. In all cases where possible, the agent should research what the suitable answer should be
> (industry standards, best practices). Cards only come to human review for clear direction where
> the agent would be unrecoverably blocked otherwise, maybe it thinks that I would choose
> differently to it and has good reasoning for that.

**So finished work does not come here to be signed off.** A card whose work is done and checked goes
to `done/`. A reviewing agent that passes a card moves it to `done/` itself. There is no step where
somebody reads good work and nods at it, because a nod carries no information and the queue exists
to protect the attention it would spend.

**Three, and only three, things put a card in this lane:**

1. **A decision that turns on a preference, a cost, a risk, or local knowledge nobody wrote down.**
   That is the same test as `## Is this actually a person's to decide?` below.
2. **A step only a person can take**, because its effect leaves the repository and no `git revert`
   reaches it: a scheduled task, a DNS record, a deploy, a message somebody receives, a browser
   check on a screen.
3. **An agent that has an answer and good reason to think the person's would differ.** This is the
   narrow one and it is not "the agent is unsure". Write the question, the research behind it, and
   what you would have chosen.

**Everything else is the agent's to settle by reading.** If the answer is established practice, an
industry standard, or written down anywhere findable, research it, apply it, and record the source
on the card. Surfacing it instead is an agent asking a person to do its reading.

**A card that arrives here without a question is a defect in that card**, not a task for the reader.

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
line to post to `## Comments`, dated and marked `**Decided:**`, written as the reader would write
it. Answering is then a copy rather than a composition, which is most of the difference between a
card answered today and one answered next month.

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
an agent picking it up can move it on if there is nothing to do. A comment that is not marked as the
answer moves nothing, because steering a card is something you do to work that is still yours to
steer.

## `## Plan` is written for a stranger

**Write it for a new session, with a new agent, holding no context at all** (Rob, 2026-08-20).
Everything that session will ever know is the prompt it was handed, this card, and whatever the card
links to. It was not in the conversation the card came out of, it has not read the other cards, and
it cannot ask.

So `## Plan` never says "as discussed", "the usual way", "the approach we agreed" or "see the
earlier card". Each of those points at something the reader does not have, and a card whose first
move is finding out what it meant has already spent the session's opening.

Two rules follow, and both are cheap:

- **Name the thing, then link it.** Not "follow the runbook" but
  `follow [docs/DEPLOY.md](../../DEPLOY.md), section One-time setup`. If what it depends on is not
  written down anywhere, writing it down is part of this card. (Shown as source, not as a live link:
  a card sits one folder deeper than this README, so the same relative path would not resolve here.)
- **Say where to stand.** Which repository, which branch, what to run first, and what "it worked"
  looks like on a screen. The unattended loop assembles all of that for its own agent and assembles
  none of it for anybody else.

**The test:** hand the card to somebody who has never seen this project and ask what they would type
first. If the honest answer is "I would go and ask", the section is not finished.

This is not care for its own sake. Every card here is worked by exactly one session that inherits
nothing, attended or not, so a plan assuming context is wrong for every reader it will ever have.

## `## Why` is the PROBLEM, and it comes before any answer

**Describe the problem before describing what might solve it.** The commonest fault on a real board
is a card that opens with candidate answers - two approaches, their costs, a recommendation - and
never once says what is actually wrong. The reader is then reverse-engineering the problem out of the
proposed solutions, which is the hardest possible way to meet a card, and they cannot tell whether
the options are the right options because they do not yet know what the options are FOR.

So `## Why` answers three things, in this order, and stops:

1. **What is wrong now.** The situation as somebody would hit it, in the real-world terms they would
   hit it in. Not the design, not the fix.
2. **What it costs.** Who is affected, how often, how much it hurts. This is what settles whether the
   card is worth doing at all.
3. **How it came to be this way**, in a sentence or two. "Nobody ever decided to leave email out, it
   just never got ticked" is the line that makes a card land.

**No solution appears in `## Why`.** The moment a fix is named there, everything after it is being
judged against that fix rather than against the problem. Solutions live in `## Options` on a decision
card and in `## Plan` on a feature card, and both read better when the section above them has
established what they are trying to achieve.

**The test, and it is a hard one:** cover everything below `## Why` and ask whether a reader who has
never seen this card could now state the problem in their own words. If they could only state your
proposed answer, the section is doing the wrong job.

## Two kinds of card, and the kind is derived

A card with `## Options` is a **decision**. A card with `## Tasks` is a **feature**. Nothing
declares its kind, because a declared kind is one more thing that can disagree with the card's own
contents.

**Decision:** `# title`, `## Why`, an optional `## Links`, `## Options` (a **numbered** list, at least
two, each with its cost), `## Recommendation`, `## Comments`. The recommendation is the point: a
decision surfaced without one hands over the whole problem, while one that recommends has done the
reading and leaves only the judgement. Its exit condition is an entry in the thread marked
`**Decided:**`.

**Feature:** `# title`, `## Why`, an optional `## Links`, `## Not this card`, `## Acceptance`,
`## Tasks`, and an optional `## Plan` that is deleted when the card reaches `done/`. `## Not this
card` is a scope fence the agent reads and obeys, and it is the cheapest defence against the
commonest agent failure, which is quietly building three adjacent things.

Acceptance uses EARS phrasing (`WHEN <trigger>, THE APP SHALL <observable result>`) inside
`<!-- AC:BEGIN -->` sentinels, so it stays greppable and interoperable with Backlog.md's
convention.

### Every criterion names the test that proves it

**A criterion ends with `proves:` and the name of the test that settles it, in backticks.**

```
<!-- AC:BEGIN -->
- [ ] WHEN a named test did not run, THE APP SHALL refuse to promote the card. proves: `it refuses promotion when a named test did not run`
- [ ] WHEN every named test ran and the suite is green, THE APP SHALL promote. proves: `it promotes when every named test ran`
<!-- AC:END -->
```

The name is the test's own description, spelled exactly as the runner prints it. In Pest that is the
`it(...)` string. Nothing is invented: run the suite once and copy the name it writes.

**Why this rule exists.** A ticked box is a self-report. For sixteen unattended sessions the ledger
recorded `ok: true` on every run, which only ever meant the process exited, and the acceptance boxes
were ticked by the same agent that wrote the code. A green suite of 330 tests proves "I broke
nothing"; it never proves "the thing asked for got built". Naming the test is what makes a criterion
a check rather than a sentence.

**It is enforced, not requested.** The build loop runs the suite with `--log-junit`, reads back the
names of the tests that actually executed, and refuses to promote a card when a criterion names a
test that did not run. That refusal is the whole point: an agent can tick a box, and it cannot make
a test appear in a run log.

**Write the test name before writing the code.** If the name cannot be written, the criterion is not
yet observable and the card is not ready. That is the cheapest signal available that a criterion is
really a wish.

### A card written after the work says so, in its first comment

Work Rob asks for directly arrives with no card. The card gets written afterwards, and every box on
it is ticked the moment it exists, because the code that satisfies them was already on disk. That
card looks exactly like one whose criteria were agreed in advance and then met. It is not one. It is
a description wearing a checklist, and a reviewer reading the ticks learns nothing.

So the first entry in `## Comments` says it plainly, dated, before any other:

```
**2026-08-25** WRITTEN AFTER THE WORK. The criteria were read back off the finished
code, so the ticks record what it does and prove nothing about what was asked for.
Attack the code, not the boxes.
```

**No frontmatter key for this**, deliberately. A key is a thing every board, both jobs and the
renderer would have to learn, and this needs to reach one reader once. The three permitted keys all
change what a machine does; this changes what a person trusts.

**The fix is upstream and it is cheap**: when Rob asks for something directly, write the criteria
first, even as three rough lines in the chat, and the card is then honest by construction. Writing
them afterwards is the fallback, not the practice.

**What is allowed to carry no test, and it must say so:** `proves: manual` for anything only a
person at a screen can settle (a browser check, a screenshot, "it looks right"), and `proves: none`
where nothing here can test it, with the reason on the same line. Both are honest and both are
counted. What is refused is a criterion that is silent about its proof, because that is the one that
reads as tested and is not.

A project with no suite the loop can find promotes as it always did, and the log says so in those
words. This rule adds a check where a suite exists; it does not invent one where none does.

## Comments: one thread, and an answer is an entry in it

`## Comments` is the card's thread, the same as comments on a JIRA card or a scrum ticket: steering,
a spike worth running first, a constraint the agent should know, a review finding, an answer. One
dated entry per post, appended and never edited.

```markdown
## Comments

**2026-08-24** Spike the parser before committing to a shape.
**2026-08-25** **Decided:** Option 2, and the cost is mine to carry.
```

**An entry beginning `**Decided:**` is the answer**, which is a decision card's exit condition and
what moves it out of `human-review/`. That mark is the whole of the distinction. There used to be
two sections, `## Direction` and `## Decided`, and the split cost a card whichever way it was got
wrong: an answer written as direction did not move the card, and steering written as a decision
closed one nobody had answered. Both were append-only dated logs read by the same code, so the only
thing the split ever did was give a writer something to get wrong.

**`## Direction` and `## Decided` are still read.** Every card written under the old split keeps
working: both headings flow into the one thread, and an entry under `## Decided` is an answer by
where it was written. Nothing is rewritten. New entries go under `## Comments`.

**The thread may be pruned, but only intentionally, and needing to is a defect report about
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
