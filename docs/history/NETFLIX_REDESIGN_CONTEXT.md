# YogaFX LMS Frontend Redesign Context

## Project Overview

YogaFX is a Learning Management System (LMS) focused on Yoga Teacher Training.

The platform contains:

* Modules
* Lessons
* Assignments
* Assessments
* Student Progress
* Certification Journey

The goal of this redesign is to transform the existing frontend experience into a Netflix-inspired learning platform while preserving all existing business logic.

---

# Critical Rule

DO NOT MODIFY:

* Backend logic
* API calls
* Database structure
* Controllers
* Services
* Models
* Routes
* Authentication flow
* Authorization flow
* Existing data fetching logic

ONLY MODIFY:

* UI
* Layout
* Styling
* Component hierarchy
* Visual presentation
* Animations
* User experience

The application must continue functioning exactly as before.

---

# Design Vision

The LMS should feel like:

Netflix + MasterClass + Premium Yoga Academy

The experience should be:

* Cinematic
* Modern
* Premium
* Immersive
* Motivating

Users should feel like they are progressing through a professional yoga training series rather than a traditional academic LMS.

---

# Color Palette

Background:

#0F0F0F

Secondary Background:

#1A1A1A

Card Hover:

#252525

Primary Accent:

#E50914

Primary Text:

#FFFFFF

Secondary Text:

#B3B3B3

Borders:

#2A2A2A

Success:

#22C55E

---

# Typography

Preferred Font:

Plus Jakarta Sans

Fallback:

Inter

Font hierarchy:

Hero Title:
48px - 72px

Section Title:
28px - 36px

Card Title:
16px - 20px

Body:
14px - 16px

---

# Homepage Structure

1. Sticky Navigation Bar

2. Hero Banner

3. Continue Learning

4. Featured Modules

5. Foundation Series

6. Teaching Methodology Series

7. Anatomy Series

8. Meditation Series

9. Assignments

10. Certification Journey

11. Footer

---

# Hero Section

The hero section should behave similarly to Netflix.

Requirements:

* Large background image
* Dark gradient overlay
* Course title
* Course description
* Continue Learning button
* Explore Modules button

Height:

80vh minimum

---

# Navigation

Dark transparent navbar.

On scroll:

Navbar becomes solid black.

Navigation items:

* Home
* Modules
* Lessons
* Assignments
* Assessments

Right side:

* Search
* Notifications
* Profile Menu

---

# Module Display

Replace traditional LMS grids with Netflix-style content rows.

Example:

Continue Learning

[Card][Card][Card][Card]

Featured Modules

[Card][Card][Card][Card]

Anatomy Series

[Card][Card][Card][Card]

Rows should support horizontal scrolling.

---

# Module Cards

Each module card should include:

* Thumbnail
* Module Title
* Progress Indicator
* Number of Lessons

Hover Effect:

scale(1.05)

Elevated shadow

Smooth transition

300ms duration

---

# Lesson Experience

Lessons should be presented like episodes.

Example:

Episode 1
Introduction to Yoga

Episode 2
History of Yoga

Episode 3
Yoga Philosophy

Each lesson card should show:

* Title
* Duration
* Completion Status

---

# Assignment Experience

Assignments should be displayed as challenge cards.

Visual terminology:

Practice Challenge

Teaching Challenge

Homework Challenge

Avoid traditional academic styling.

---

# Assessment Experience

Assessment pages should feel like certification exams.

Visual terminology:

Certification Exam

Final Evaluation

Progress Checkpoint

---

# Progress Tracking

Add visually engaging progress indicators.

Examples:

* Progress bars
* Circular progress
* Learning streaks
* Completion badges

---

# Animations

Use subtle animations.

Card Hover:

transform: scale(1.05)

Buttons:

translateY(-2px)

Transitions:

200ms - 300ms

Avoid excessive animation.

---

# Accessibility

Maintain:

* Keyboard navigation
* Focus states
* Screen reader compatibility
* Color contrast

---

# Responsive Design

Must work on:

* Mobile
* Tablet
* Desktop

Horizontal content rows should become swipeable on mobile.

---

# Implementation Strategy

Phase 1

* Homepage redesign
* Hero section redesign
* Navbar redesign

Phase 2

* Module listing redesign
* Module detail redesign

Phase 3

* Lesson experience redesign

Phase 4

* Assignment redesign
* Assessment redesign

Phase 5

* Animations
* Micro interactions
* Final polish

---

# Final Goal

The platform should visually resemble Netflix while remaining a professional Yoga Teacher Training LMS.

Functionality must remain unchanged.

Only the frontend presentation should be modernized.