# Task Management App - Feature Enhancement Plan

## Overview

Adding three major features to the existing task management web app:

1. Forgot Password System
2. Task Reminder System
3. Task Countdown Timer

## Current App Analysis

- [check] User authentication (login/register)
- [check] Task CRUD operations
- [check] Task reflection system
- [check] Analytics dashboard
- [check] Dark/light theme
- [check] LocalStorage data persistence

## Required Enhancements

### 1. Forgot Password System

**Components to Add:**

- "Forgot Password" link on login page
- Forgot password modal with email input
- Reset password modal with token validation
- Password reset confirmation

**Implementation Details:**

- Store reset tokens in localStorage with expiration
- Use Resend API to send reset emails
- Client-side token validation
- Update user passwords in localStorage
- Clear UX feedback and validation

### 2. Task Reminder System

**Components to Add:**

- Reminder settings in task creation/editing modal
- Reminder options: "1 day before", "30 minutes before", "custom time"
- Background reminder checker
- Browser notification system
- Email reminder via Resend API

**Implementation Details:**

- Store reminder settings in localStorage
- Periodic check for due reminders (every minute)
- Browser notification permissions handling
- Resend API integration for email reminders
- Visual indicators for tasks with reminders

### 3. Task Countdown Timer

**Components to Add:**

- Due time field in task creation/editing
- Real-time countdown display on task cards
- Timer state persistence across page reloads
- Visual alerts when timer expires
- Email notification on deadline

**Implementation Details:**

- Store due time in task data
- Real-time timer updates (every second)
- Timer state preservation in localStorage
- Visual countdown display with color coding
- Automated email notifications

## Technical Implementation

### Files to Modify:

1. **HTML Structure:**
     - Add forgot password modals
     - Add reminder settings to task form
     - Add due time field to task form
     - Update task cards with countdown timer

2. **CSS Styling:**
     - Modal styles for forgot password
     - Timer display styles
     - Reminder indicator styles
     - Notification styles

3. **JavaScript Logic:**
     - Forgot password functionality
     - Reminder system with setInterval
     - Countdown timer with setInterval
     - Resend API integration
     - Browser notification handling
     - Enhanced localStorage management

### New localStorage Keys:

- `passwordResetTokens` - Store reset tokens with expiration
- `reminderChecks` - Track last reminder check time
- `timerStates` - Preserve countdown timer states

### Resend API Integration:

- Password reset emails
- Task reminder emails
- Deadline notification emails
- Error handling for API failures

## Implementation Steps:

1. [check] Plan created and user confirmation
2. [refresh] Update HTML structure for new features
3. [refresh] Add CSS styles for new components
4. [refresh] Implement JavaScript functionality
5. [refresh] Add Resend API integration
6. [refresh] Test all features thoroughly
7. [refresh] Deploy and validate

## Expected Deliverables:

- Complete updated HTML file
- Complete updated CSS (integrated)
- Complete updated JavaScript (integrated)
- All features working locally
- Clean, commented code
- Resend API examples included
