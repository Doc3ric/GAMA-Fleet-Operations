# GAMA Fleet Operations Management System
# Development Workflow & Architecture

## 1. Project Purpose

This is an existing Laravel 11 internal fleet operations and GPS monitoring management system.

The system is used by a GPS Fleet Monitoring Specialist to organize, document, automate, and generate reports based on fleet monitoring data.

IMPORTANT:

TrackSolidPro remains the actual GPS monitoring source/platform.

This Laravel system is NOT intended to replace TrackSolidPro.

The Laravel system is an internal workflow, reporting, documentation, and fleet-management assistant.

Do not attempt to replace TrackSolidPro's GPS infrastructure unless explicitly requested.

---

# 2. Existing Technology

Current backend:

- Laravel 11
- PHP
- Blade
- Livewire
- Alpine.js
- MySQL / existing Laravel database
- Maatwebsite Excel
- DomPDF

Existing system already contains working modules.

DO NOT rewrite the existing system.

DO NOT replace the existing architecture unnecessarily.

DO NOT delete or break existing functionality.

Reuse existing models, migrations, services, policies, components, layouts, and database conventions whenever appropriate.

---

# 3. Existing Modules

Current working modules include:

## Authentication

- Login
- Registration
- Logout
- Rate limiting
- Forgot password
- Optional email verification
- Profile update
- Change password
- Account deletion

---

## Dashboard

Route:

/dashboard

Current functionality includes:

- Today's Long Idling report status
- Total reports
- Total records
- Quick actions
- Recent reports

---

## Vehicle Master List

Route:

/vehicles

Current functionality includes:

- Vehicle listing
- Pagination
- Search
- Filters
- Add vehicle
- Edit vehicle
- Delete vehicle
- Vehicle image
- Excel/CSV import
- Import modes
- Vehicle type handling
- GPS status

Vehicle information includes:

- Equipment Code
- Vehicle Type
- Model
- Plate Number
- Date Acquired
- Fuel
- Status
- Location
- Project Code
- Operator/Driver
- Helper
- GPS Status
- Notes
- Image

GPS Status values currently include:

- YES
- NO
- EXPIRED
- FOR CHECKUP

IMPORTANT:

The existing vehicles table/master list should be reused.

Do NOT create a second vehicle master list unless absolutely necessary.

---

# 4. Existing Long Idling System

Route:

/reports

The Long Idling system currently supports:

- Daily reports
- Draft/Completed status
- Duplicate previous report
- Report editing
- Report deletion
- PDF export
- Excel export
- CSV/TXT import
- Screenshot/image attachments

Long Idling records contain information such as:

- Device Name
- IMEI
- Model
- State
- Start Time
- End Time
- Stay Time
- Latitude
- Longitude
- Address
- Screenshot/Image
- Remarks
- Sort Order

Existing PDF and Excel reporting architecture should be reused where possible.

---

# 5. Planned New Feature: Driver Itinerary

A new Driver Itinerary module will be added to the existing Laravel system.

The purpose is to automatically collect simple trip/itinerary information from drivers using a mobile application.

The driver should NOT need to manually type origin and destination addresses.

The mobile application should capture:

- Current GPS coordinates
- Current date
- Current time
- Origin location
- Destination location

The driver workflow should be extremely simple.

---

# 6. Driver Mobile Application

The mobile application will be a REAL installed Android application.

Technology:

- React Native
- TypeScript
- React Native CLI
- Android Studio
- Android SDK

IMPORTANT:

DO NOT use Expo.

DO NOT use Expo Go.

DO NOT migrate the application to Flutter.

The mobile application should be built using React Native CLI.

---

# 7. Driver App V1 Workflow

Driver opens the application.

### Login

Driver logs in.

After login:

- Display driver's name
- Display assigned vehicle
- Display current trip status

---

### Start Trip

Driver presses:

START TRIP

The application automatically captures:

- Date
- Time
- Latitude
- Longitude
- GPS accuracy
- Origin address

A trip is then created with status:

IN_PROGRESS

The driver does not manually type the origin.

---

### During Trip

The driver simply drives.

V1 SHOULD NOT continuously track GPS.

Do not implement background tracking unless explicitly requested later.

---

### End Trip

Driver presses:

END TRIP

The application captures:

- Date
- Time
- Latitude
- Longitude
- GPS accuracy
- Destination address

The trip status becomes:

COMPLETED

The driver does not manually type the destination.

---

# 8. Driver Itinerary V1 Data

The logical trip data should contain:

- id
- driver_id
- vehicle_id
- trip_date
- time_in
- time_out
- origin_latitude
- origin_longitude
- origin_address
- destination_latitude
- destination_longitude
- destination_address
- status
- remarks
- created_at
- updated_at

Possible statuses:

- IN_PROGRESS
- COMPLETED
- CANCELLED

Do not add unnecessary fields without first evaluating the existing architecture.

---

# 9. Driver and Vehicle Relationship

Drivers should be associated with the existing user/driver structure if one already exists.

Vehicles should use the existing vehicles table.

Do NOT duplicate vehicle records inside the Driver Itinerary module.

Do NOT store driver names as plain text when a proper relationship can be used.

The system should support:

Driver → Assigned Vehicle

The exact implementation must be determined after inspecting the existing project.

---

# 10. Offline-First Mobile App

The mobile application should work even when the driver's phone temporarily has no internet.

Use local SQLite storage on the Android device.

Example:

Driver starts trip
        ↓
GPS captured
        ↓
Saved locally
        ↓
Driver drives
        ↓
Driver ends trip
        ↓
Destination GPS captured
        ↓
Saved locally
        ↓
Internet available
        ↓
Synchronize with backend

The app should NOT lose a trip because of temporary internet loss.

---

# 11. Sync Requirements

Synchronization must be designed to prevent duplicate trips.

Use a unique client-generated identifier / idempotency key for mobile-created trips.

If a trip has already been synchronized, sending the same trip again must not create a duplicate.

Sync should support:

- Pending
- Syncing
- Synced
- Failed

The exact implementation should be determined after inspecting the Laravel architecture.

---

# 12. Address Resolution

GPS coordinates are authoritative.

Addresses are derived from coordinates.

The application/system may use reverse geocoding to convert:

Latitude + Longitude

into:

Human-readable Address

Coordinates must always be preserved even if reverse geocoding fails.

Do NOT treat a manually typed address as more authoritative than GPS coordinates.

The exact reverse-geocoding solution should be selected during implementation planning.

Prefer free/open solutions where practical.

---

# 13. Laravel Mobile API

The Laravel application will eventually expose secure API endpoints for the mobile application.

Possible API structure:

POST /api/mobile/login

POST /api/mobile/trips/start

POST /api/mobile/trips/{trip}/end

GET /api/mobile/trips

GET /api/mobile/profile

GET /api/mobile/vehicle

POST /api/mobile/sync

These are proposed endpoints only.

DO NOT create them blindly.

Inspect the existing project first and determine:

- Existing API routes
- Existing authentication
- Existing middleware
- Existing user roles
- Existing policies
- Existing models
- Existing database conventions

Then propose the correct API architecture.

---

# 14. Security

Mobile users must only be allowed to access their own driver information and trips.

Drivers must NOT be able to:

- View other drivers' trips
- Modify another driver's trips
- Change another driver's assigned vehicle
- Access administrator functions

Administrative/monitoring users may view all itinerary records depending on existing authorization rules.

All authorization must be enforced server-side.

Never rely only on mobile UI restrictions.

---

# 15. Weekly Itinerary Report

The Laravel system should eventually provide:

GENERATE WEEKLY ITINERARY

Filters may include:

- Date range
- Driver
- Vehicle

The generated report should contain:

| Date | Driver | Vehicle | Origin | Destination | Time In | Time Out |

Future fields may include:

- Distance
- Odometer
- Purpose
- Remarks
- GPS coordinates

But these are NOT required for V1.

Existing PDF and Excel generation systems should be reused.

---

# 16. Future Architecture

Potential architecture:

React Native Mobile App
        ↓
SQLite Offline Storage
        ↓
Sync/API
        ↓
Laravel Backend
        ↓
Existing Database
        ↓
Laravel Web System
        ↓
Weekly Itinerary PDF / Excel

Supabase may be considered as a cloud/shared database or synchronization layer if required.

IMPORTANT:

Do NOT introduce Supabase automatically.

First inspect the existing Laravel database and architecture.

The final architecture must have one clear source of truth.

Avoid creating two databases that independently modify the same business data without a clear synchronization strategy.

---

# 17. Important Existing System Rules

The existing system is already functional.

Therefore:

DO NOT:

- Rewrite the Laravel project
- Rebuild authentication unnecessarily
- Replace Livewire unnecessarily
- Replace Blade unnecessarily
- Replace the existing Vehicle Master
- Delete existing migrations
- Delete existing modules
- Replace the existing report system
- Modify unrelated features
- Introduce unnecessary packages
- Create duplicate vehicle databases
- Create duplicate driver databases
- Build TrackSolidPro replacement functionality

Always prefer integration over replacement.

---

# 18. Development Stages

Development should happen in stages.

## Phase 0 — Project Inspection

Inspect the entire existing Laravel project.

Do not modify files.

Produce an architecture report and implementation plan.

---

## Phase 1 — Driver/Itinerary Backend

Implement:

- Driver relationship if needed
- Vehicle assignment
- Itinerary database
- Models
- Relationships
- Policies
- Validation

---

## Phase 2 — Laravel Mobile API

Implement secure mobile endpoints.

---

## Phase 3 — Laravel Driver Itinerary UI

Implement:

- Driver itinerary listing
- Filters
- Trip details
- Weekly report generation
- PDF
- Excel

---

## Phase 4 — React Native Application

Create:

Gama Driver App

Features:

- Login
- Assigned vehicle
- Start Trip
- End Trip
- GPS capture
- Address resolution
- Local SQLite
- Sync status

---

## Phase 5 — Offline Synchronization

Implement reliable:

- Local storage
- Queue
- Sync
- Retry
- Duplicate protection
- Error handling

---

## Phase 6 — Pilot Testing

Test with:

- One driver
- One vehicle
- Real Android phone

Only after successful testing should the system be expanded.

---

# 19. V1 Scope

V1 goal:

Driver Login
→ Assigned Vehicle
→ START TRIP
→ Capture GPS + Time
→ END TRIP
→ Capture GPS + Time
→ Save Trip
→ Synchronize
→ Laravel displays trip
→ Generate Weekly Itinerary
→ PDF / Excel

---

# 20. Explicitly Out of Scope for V1

Do NOT build these unless explicitly requested:

- Live fleet GPS tracking
- TrackSolidPro replacement
- GPS device integration
- Continuous background tracking
- Geofencing
- Automatic odometer
- Fuel monitoring
- Advanced analytics
- AI itinerary generation
- Complex route optimization
- Driver behavior scoring

These may be future features.

---

# 21. Antigravity Development Rule

Before writing code:

1. Read this WORKFLOW.md completely.
2. Inspect the existing Laravel project.
3. Understand the existing architecture.
4. Identify reusable components.
5. Identify conflicts or risks.
6. Propose the implementation plan.
7. Wait for approval.

Never assume the project's structure.

Never blindly create new architecture when an existing implementation can be reused.

When making changes, keep them small, staged, testable, and reversible.

Existing working functionality has priority over new functionality.