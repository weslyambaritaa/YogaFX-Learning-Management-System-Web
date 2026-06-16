# YogaFX LMS — Netflix UX Transformation Master Blueprint

## Objective

Transform the Student Area of YogaFX LMS into a Netflix-inspired learning experience.

The goal is NOT to redesign the learning system.

The goal is NOT to modify business logic.

The goal is NOT to rebuild backend architecture.

The goal is to preserve 100% of existing learning logic while transforming the user experience so students feel like they are using Netflix for Yoga Education.

---

# Critical Rules

## Never Change

The following systems must remain untouched:

- Authentication
- Authorization
- Student permissions
- Progress tracking logic
- Lesson completion logic
- Module completion logic
- Certification logic
- Unlock sequencing
- Database schema
- Controllers
- API endpoints
- Routes
- Validation logic
- Admin Area
- Admin UI
- Admin workflow

Only Student Area presentation may be modified.

---

# Transformation Philosophy

Current UX:

Learning Management System

Target UX:

Netflix for Yoga Education

Students should feel:

"I am streaming my journey to become a Yoga Teacher."

NOT

"I am using an online course platform."

---

# Netflix Principles

## Principle 1

Content First

Content is the hero.

Statistics are secondary.

Administrative information is hidden.

---

## Principle 2

Visual Discovery

Students should discover learning through visual cards.

Avoid dashboard-style panels.

Avoid information overload.

---

## Principle 3

Continue Learning

The primary action should always be:

Continue Learning

not

View Statistics

not

View Reports

not

Manage Account

---

## Principle 4

Horizontal Rows

Learning content must be organized into Netflix-style rows.

Examples:

Continue Learning

Foundation Training

Teaching Methodology

Certification Journey

Assessment Center

Resources

Recommended Next

---

# Student Home Page Structure

## Hero Banner

Replace dashboard hero layout with Netflix hero layout.

Hero contains:

- Featured Learning Path
- Program title
- Short description
- Continue Learning button
- Explore Program button

Large background image or video.

Dark cinematic overlay.

Minimal text.

No dashboard cards.

No statistics cards.

No admin-style information.

---

## Hero CTA

Primary CTA

Continue Learning

Secondary CTA

Program Details

---

# Remove From Hero

Do not display:

- Total Access Time
- Student Context
- Active Access Tier
- Technical Metadata
- Learning Metrics

These may still exist in backend logic.

Do not show them prominently.

---

# Home Page Sections

## Row 1

Continue Learning

Display:

- Current lesson
- Progress percentage
- Module title

Netflix equivalent:

Continue Watching

---

## Row 2

Foundation Training

Display module cards.

Horizontal scroll.

---

## Row 3

Teaching Skills

Display module cards.

Horizontal scroll.

---

## Row 4

Certification Journey

Display certification milestones.

Horizontal scroll.

---

## Row 5

Resources

Display:

- Workbook
- Ebook
- PDF
- Attachments

As content cards.

Not dashboard cards.

---

## Row 6

Recommended Next

Display recommended lessons.

Horizontal scroll.

---

# Card Design

Replace LMS cards with Netflix-style cards.

Card ratio:

16:9

Card contains:

- Thumbnail
- Title
- Progress

Minimal text.

Avoid large descriptions.

---

# Card Hover Behavior

On hover:

scale(1.08)

elevate above neighboring cards

show overlay

show:

Continue Learning

Progress %

Lesson count

Duration

Smooth animation.

---

# Navigation

Keep existing routes.

Keep existing navigation logic.

Improve visual presentation only.

Navigation structure:

Home

Modules

Certification

Profile

Use Netflix spacing and hierarchy.

---

# Typography

Netflix-inspired hierarchy:

Hero Title:
Very Large

Section Title:
Large

Card Title:
Medium

Metadata:
Small

Reduce paragraph density.

Avoid large text blocks.

---

# Dashboard Metrics

Current metrics:

- Overall Progress
- Lessons Finished
- Modules Finished
- Momentum Summary

Do not remove data.

Transform presentation.

Example:

Instead of:

Overall Progress: 65%

Use:

Continue Learning
65% Complete

---

# Module Page Transformation

Current concept:

Course Detail Page

Target concept:

Netflix Series Detail Page

Structure:

Hero Banner

Program Description

Progress

Continue Learning

Lesson List

Lessons appear as Episodes.

---

# Lesson Transformation

Current concept:

Lesson

Target concept:

Episode

Display:

Episode Thumbnail

Episode Title

Duration

Completed Status

Continue Button

---

# Color System

Primary Background

#141414

Secondary Background

#181818

Card Background

#202020

Text

#FFFFFF

Secondary Text

#B3B3B3

Accent

Use YogaFX Brand Accent

Do not copy Netflix red directly.

---

# Animation Rules

Use:

transform

opacity

scale

transition

Avoid:

heavy parallax

complex motion

excessive effects

Netflix is elegant and minimal.

---

# Responsive Behavior

Desktop

Netflix-like rows

Tablet

Reduced card count

Mobile

Swipeable horizontal rows

Preserve Netflix feeling on all screen sizes.

---

# Success Criteria

A student opening YogaFX should immediately feel:

"This looks and feels like Netflix."

while all existing learning logic remains unchanged.

Only the visual presentation layer should be transformed.

No backend logic changes are permitted.
