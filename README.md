# Internship App — AAF Inventory Management System

A web application I built during my internship to help teams manage IT equipment, inventory cycles, transfers, and repair workflows in one place.

## Why I built this

This project was designed to replace manual tracking with a centralized system where admins can:
- manage hardware records,
- assign devices to users/services,
- track lifecycle status changes,
- run inventory checks,
- and keep repair/transfer history.

## What the app does

### Core modules
- **Authentication**: login/register system (first registered user becomes admin).
- **Material management**: add, edit, delete, filter, and classify equipment.
- **Inventory workflow**: start inventory sessions, mark present/missing items, recover inventoried items.
- **User management**: maintain users and service assignments.
- **Reference data**: manage brands, types, services, and suppliers.
- **Maintenance / repair**: send equipment to repair and track repair history.
- **History tracking**: record state/user transitions for traceability.
- **Exports**:
  - CSV export (`php/export_excel.php`)
  - print-ready HTML/PDF exports (`export_pdf.php`, `reparation_history.php`)

### Additional functionality
- Department/environment filtering (PROD/COMM).
- Material state tracking (En service, En stock, Endommagé, Cassé).
- Multi-item material creation flow.

## Tech stack

- **Backend**: PHP (PDO)
- **Frontend**: HTML, CSS, JavaScript
- **Database**: MySQL
- **Mail library**: PHPMailer (Composer dependency)

## Project structure

```text
/
├── index.php                 # Main application (tabs + CRUD + workflows)
├── login.php / register.php  # Authentication pages
├── fiche_reparation.php      # Repair form/workflow
├── reparation_history.php    # Repair history export view
├── get_material_history.php  # Material transfer/state history view
├── export_pdf.php            # Print-ready global export page
├── php/
│   ├── config.php            # Database connection
│   ├── initialize_db.php     # Auto-creates helper tables if missing
│   └── export_excel.php      # CSV export endpoint
├── sql/                      # SQL scripts for supporting tables
├── css/ / js/                # UI styles and interactions
└── imgs/                     # UI assets
```

## Setup

### 1) Requirements
- PHP 8.x
- MySQL / MariaDB
- Web server (Apache, Nginx, or local stack like XAMPP/WAMP)

### 2) Install dependencies
```bash
composer install
```

### 3) Configure database
Update `/php/config.php`:
- `host`
- `db`
- `user`
- `pass`

The app auto-creates some support tables via `php/initialize_db.php` when `index.php` loads.

### 4) Run
Serve the repository with PHP/web server and open:
```text
http://localhost/<project-folder>/login.php
```

## Usage flow

1. Register your first account (it becomes admin).
2. Login and open the main dashboard.
3. Configure base data (users, types, brands, services, suppliers).
4. Add materials and assign users/services.
5. Use inventory and maintenance tabs for operations and tracking.
6. Export data for reporting/recruitment/demo purposes.

## Notes

- UI labels are mainly in French (target operational users).
- For production deployment, update SMTP and sensitive credentials through environment-secure configuration.

## Author

Built by **@Young-Xster** during internship work.
