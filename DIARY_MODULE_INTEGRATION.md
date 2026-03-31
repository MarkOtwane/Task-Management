# Daily Diary (Thought Canvas) Integration Guide

This guide explains the new, isolated Daily Diary module added to the existing Task Management system.

## What Was Added

The Diary feature is fully modular and independent of task logic:

- New frontend Diary tab with calm writing interface
- Optional voice recording and upload for each entry
- New backend API endpoint dedicated to diary operations
- New database table `diary_entries` (no changes to existing task tables or task APIs)

## Files Added

- `backend/api/diary.php`
- `uploads/diary/.gitkeep`
- `DIARY_MODULE_INTEGRATION.md`

## Files Updated

- `backend/config/database.php`
- `backend/TaskAPI.js`
- `backend/index.php`
- `index.html`

## UI Components Included

The Diary UI is available under **My Thoughts** in the sidebar.

The section includes:

- Date field
- Title field (auto-defaults to the selected date)
- Large writing area for thoughts
- Mood selector (optional)
- Voice recording controls:
     - Record Voice (start/stop)
     - Clear Voice
     - Audio preview
- Save Diary Entry button
- Diary history list
- Diary entry detail panel (with optional audio playback)

## Backend API

Base path:

- `/backend/api/diary.php`

Endpoints:

1. `GET /api/diary.php`

- Returns all diary entries for authenticated user

2. `GET /api/diary.php?id={entryId}`

- Returns one diary entry for authenticated user

3. `POST /api/diary.php`

- Creates a diary entry
- Supports:
     - JSON body (text-only entry)
     - `multipart/form-data` (text + optional `audio_note` file)

Supported audio formats:

- `audio/webm`
- `audio/wav`
- `audio/mpeg`
- `audio/mp4`
- `audio/ogg`

Max audio size:

- 10MB

## Database Schema Update

A new table is created automatically by `backend/config/database.php` initialization:

```sql
CREATE TABLE IF NOT EXISTS diary_entries (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    entry_date DATE NOT NULL DEFAULT CURRENT_DATE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(50),
    audio_file_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_diary_entries_user_date
ON diary_entries(user_id, entry_date DESC, created_at DESC);
```

## Integration Notes

- The Diary module does not modify:
     - task table structure
     - task CRUD APIs
     - reminder/reflection task logic
- Diary state is loaded and cleared independently from tasks.
- Diary audio files are stored in `uploads/diary/`.

## Quick Test Flow

1. Log in.
2. Open **My Thoughts** from sidebar.
3. Write content and save.
4. Confirm entry appears in history.
5. Click entry to view detail.
6. Record voice note, stop recording, save another entry.
7. Open that entry and confirm audio playback.

## Optional API Example (JSON)

```bash
curl -X POST http://localhost/backend/api/diary.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "entry_date": "2026-03-31",
    "title": "March Reflection",
    "content": "Today I felt focused and calm.",
    "mood": "calm"
  }'
```

## Optional API Example (Multipart + Audio)

```bash
curl -X POST http://localhost/backend/api/diary.php \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "entry_date=2026-03-31" \
  -F "title=Voice Journal" \
  -F "content=I wanted to capture this thought quickly." \
  -F "mood=happy" \
  -F "audio_note=@/path/to/voice.webm"
```
