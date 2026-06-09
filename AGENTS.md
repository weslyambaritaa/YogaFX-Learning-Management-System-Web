# AGENTS.md

## Project Overview

YogaFX LMS is a premium web-based learning platform for YogaFX. It is not a traditional academic LMS. The product serves two main roles: **Admin** and **Student**.

The system supports:
- structured digital learning
- tier-based content access
- sequential lesson progression
- assessment flow with timer and autosave
- assignment submission and review
- certificate generation
- email notification templates and trigger-based delivery

The product experience must follow two distinct directions:
- **Student Side**: premium, calm, streaming-inspired learning experience
- **Admin Side**: professional, structured, management-oriented dashboard

The stack and architecture direction are already decided. Do not redesign them during implementation.

---

## Source of Truth Documents

Before implementing any feature, read the documentation in the following order:

1. `docs/01-prd.md`
2. `docs/02-user-flow.md`
3. `docs/03-business-rules.md`
4. `docs/04-erd.md`
5. `docs/05-information-architecture.md`
6. `docs/06-modular-implementation.md`

### Reading Order Rules
- `01-prd.md` defines **what the product must do**
- `02-user-flow.md` defines **how users move through the system**
- `03-business-rules.md` defines **what the system must enforce**
- `04-erd.md` defines **the data model and entity relationships**
- `05-information-architecture.md` defines **how pages and features are organized**
- `06-modular-implementation.md` defines **the build order and implementation strategy**

You must not skip this reading order.

---

## Diagram Location

All Mermaid diagrams are located in:

- `docs/mermaid/`

Use these diagrams as visual support only.  
If a Mermaid diagram conflicts with the written documentation, trust the written documentation first, using the conflict rules defined below.

---

## Core Development Rules

1. **Follow the approved scope only.**
   Do not invent features outside the documented requirement.

2. **Build incrementally.**
   Implement one domain at a time.

3. **Respect dependency order.**
   Do not implement downstream domains before upstream domains are stable.

4. **Do not redesign the architecture.**
   The project already has a decided architecture and stack.

5. **Use the approved product model.**
   This is a premium wellness learning platform, not a traditional school LMS.

6. **Student side and Admin side are different products within one system.**
   Do not mix their mental models.

7. **Business rules are mandatory.**
   Never ignore access tier rules, lesson locking rules, assessment rules, assignment rules, certificate rules, or email rules.

8. **Data model must follow the ERD.**
   Do not add, remove, or reshape entities without explicit requirement support.

9. **UI must follow the Information Architecture and Design direction.**
   Do not create screens, flows, or navigation patterns outside the documented structure.

10. **Do not over-engineer phase 1.**
    Build what is required, no more.

---

## Coding Principles

1. **Clarity over cleverness**
   Write code that is easy to read, review, and extend.

2. **Maintainability first**
   Prefer explicit, modular, understandable implementation.

3. **Reusability where patterns repeat**
   Reuse components, patterns, and logic only when the abstraction is genuinely helpful.

4. **Business rules before UI polish**
   Correct flow and validation come before visual enhancement.

5. **Keep domains isolated**
   Implement per domain, not by random file edits across the whole system.

6. **Do not create speculative abstractions**
   Do not build future-proof layers unless current requirements need them.

7. **Respect role boundaries**
   Student-facing logic and admin-facing logic must remain clearly separated.

8. **Respect product tone**
   Student-facing UI should feel premium and calm. Admin-facing UI should feel structured and efficient.

9. **Avoid hidden logic**
   Important business rules must be implemented in predictable, reviewable places.

10. **Do not bypass requirements with shortcuts**
    A fast implementation that breaks the PRD or Business Rules is not acceptable.

---

## Implementation Workflow

For every domain or feature, follow this workflow:

### Step 1 — Read First
Before touching code, read the relevant sections from:
- PRD
- User Flow
- Business Rules
- ERD
- Information Architecture
- Modular Implementation Guide

### Step 2 — Confirm Domain Scope
Identify:
- which domain you are working on
- what the domain depends on
- what must already exist before starting

### Step 3 — Implement in Layer Order
Implement in this sequence unless the domain specifically requires another order:

1. **Data layer**
2. **Backend/domain logic**
3. **Frontend/page flow**
4. **Integration points**

### Step 4 — Validate Against Rules
Check:
- user flow correctness
- business rule compliance
- entity relationship consistency
- role and tier restrictions
- page placement in IA

### Step 5 — Stop at Domain Boundary
Do not continue into adjacent domains unless explicitly asked or required by dependency completion.

### Step 6 — Report What Changed
When finishing a task, clearly state:
- what was implemented
- which documents were followed
- what remains out of scope

---

## Definition of Done

A domain or feature is only considered done when all of the following are true:

1. **Requirement alignment**
   - matches the PRD
   - matches the User Flow
   - matches the Business Rules
   - matches the ERD
   - matches the Information Architecture

2. **Functional completeness**
   - the main use case works end-to-end
   - required validations are in place
   - required state transitions are handled

3. **Boundary correctness**
   - role restrictions work
   - tier restrictions work
   - ownership restrictions work
   - locked/unlocked states work where applicable

4. **UI correctness**
   - the feature appears in the correct place
   - the interaction flow matches the documented journey
   - the experience matches student/admin intent

5. **No unauthorized scope expansion**
   - no extra features were added without requirement support

6. **Code quality**
   - readable
   - maintainable
   - consistent with project direction

A feature is **not done** just because the page renders.

---

## Conflict Resolution Rules

If you detect conflicts between documents, follow this order:

### Rule 1 — Latest agreed revision wins
The latest explicitly agreed project decision overrides older assumptions.

### Rule 2 — Written requirement beats diagram
If a Mermaid diagram conflicts with written documentation, trust the written documentation.

### Rule 3 — Business Rules override convenience
If implementation convenience conflicts with Business Rules, follow the Business Rules.

### Rule 4 — PRD defines scope
If a feature appears implied elsewhere but is not supported by the PRD or approved revisions, do not add it.

### Rule 5 — Information Architecture defines placement
If a feature exists but page placement is unclear, follow the IA document.

### Rule 6 — ERD defines entity boundaries
If implementation ideas require changing entities or relationships, do not do it unless the requirement truly supports it.

### Rule 7 — Ask before inventing
If a conflict cannot be resolved from the documents, stop and surface the ambiguity instead of guessing.

---

## Rules Against Building Outside Requirement

You must not:

- create new features not defined in the approved documentation
- invent new roles beyond Admin and Student for phase 1
- add new domains outside the documented system scope
- add new entities without strong requirement support
- add extra workflow steps not present in the documented user flow
- create advanced reporting, analytics, or automation outside approved scope
- redesign the product into a traditional academic LMS
- replace the streaming-inspired student experience with school-style UI patterns
- introduce architecture changes that were not approved
- introduce implementation shortcuts that weaken business rule enforcement

If something seems useful but is not clearly required, treat it as **out of scope** unless explicitly approved.

---

## Product Experience Guardrails

### Student Side
Always preserve:
- premium learning feel
- calm visual flow
- streaming-inspired content discovery
- strong Continue Learning experience
- visible but non-academic-feeling progress
- clear next step guidance

Do **not** make the student side feel like:
- Moodle
- Google Classroom
- Blackboard
- Canvas LMS
- generic campus e-learning portals

### Admin Side
Always preserve:
- clarity
- operational efficiency
- structured workflows
- strong status visibility
- professional dashboard behavior

---

## Final Instruction to AI Coding Assistant

Before implementing anything:
1. read the required documents in order
2. identify the current domain
3. confirm dependencies
4. implement only within scope
5. validate against rules
6. stop when the requested domain is complete

Do not guess.  
Do not improvise requirements.  
Do not expand scope silently.  
Build YogaFX LMS exactly as defined.