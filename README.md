#CanTech — Canteen Management System

> A hybrid web-based system combining **Online Transaction Processing (OLTP)** and **Online Analytical Processing (OLAP)** for smart canteen operations and business intelligence.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=flat)

---

##  Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Installation](#installation)
- [Default Accounts](#default-accounts)
- [Usage Guide](#usage-guide)
- [Database Design](#database-design)
- [OLAP Operations](#olap-operations)
- [Team](#team)

---

##  Overview

**CanTech** is a full-stack canteen management system developed as a final project for:
- **DCIT 55B** — Advanced Database System
- **ITEC 65A** — Open-Source Technologies

The system manages daily canteen transactions (OLTP) while simultaneously providing analytical dashboards and reports (OLAP) for data-driven business decisions.

---

##  Features

### Transactional (OLTP)
-  Full ACID-compliant order processing
-  Menu management with availability toggle
-  Ingredient inventory with restock tracking
-  Customer management with loyalty points system
-  Role-based access control (Admin & Cashier)
-  Secure login with bcrypt password hashing

### Analytical (OLAP)
-  Star Schema data warehouse
-  ETL pipeline (OLTP → OLAP sync)
-  Roll-up, Drill-down, Slice, Dice operations
-  Interactive Chart.js dashboard
-  PDF and CSV report export

### Extra Features
-  Loyalty points with redemption (100 pts = ₱10 discount)
-  Low stock alerts with visual progress bars
-  Customer searchable dropdown in order modal
-  Best seller badges with medal rankings
-  Warm food-themed UI (Poppins font, orange accent)

---

##  Tech Stack

| Layer | Technology | Reason |
|---|---|---|
| Backend | PHP 8.0+ | Server-side logic, session management, ACID transactions |
| Database | MySQL 8.0 | Relational integrity for OLTP; Star Schema for OLAP |
| Frontend | HTML5, CSS3, Bootstrap 5.3 | Responsive, mobile-friendly UI |
| Charts | Chart.js | Interactive analytical visualizations |
| PDF Export | FPDF 1.84 | Lightweight PHP PDF generation |
| Local Server | XAMPP | Apache + MySQL development environment |
| Typography | Google Fonts (Poppins) | Professional, readable UI font |
| Version Control | Git + GitHub | Collaboration and source code management |

---

##  Project Structure

```
cantech/
├── config/
│   ├── db.php              # Database connections (OLTP + OLAP)
│   └── auth.php            # Session & role-based access helpers
├── dashboard/
│   └── index.php           # Analytics dashboard (Admin only)
├── includes/
│   ├── sidebar.php         # Shared navigation sidebar
│   └── sidebar_style.php   # Shared CSS theme
├── libs/
│   └── fpdf/               # FPDF PDF generation library
├── olap/
│   ├── etl.php             # ETL pipeline runner
│   └── queries.php         # OLAP operations (Roll-up, Drill-down, Slice, Dice)
├── oltp/
│   ├── orders.php          # Order management + ACID transactions
│   ├── menu.php            # Menu CRUD
│   ├── inventory.php       # Ingredient inventory + restock
│   ├── customers.php       # Customer + loyalty points management
│   ├── users.php           # User account management (Admin)
│   └── get_order.php       # AJAX order details endpoint
├── reports/
│   └── export.php          # PDF + CSV report export
├── database/
│   ├── canteen_oltp.sql    # OLTP database schema + seed data
│   └── canteen_olap.sql    # OLAP Star Schema
├── index.php               # Homepage
├── login.php               # Login page
├── logout.php              # Session destroy
├── unauthorized.php        # Access denied page
└── README.md               # This file
```

---

##  Installation

### Prerequisites
- XAMPP v8.0 or higher
- PHP 8.0+
- MySQL 8.0+
- Modern web browser (Chrome, Firefox, Edge)

### Step 1 — Clone the Repository
```bash
git clone https://github.com/cyanmwehehehe/SCHOOL-PROJECT.git
```
Or download the ZIP and extract it.

### Step 2 — Move to XAMPP
Copy the project folder into:
```
C:\xampp\htdocs\cantech\
```

### Step 3 — Start XAMPP
Open XAMPP Control Panel and start **Apache** and **MySQL**.

### Step 4 — Create Databases
1. Open `http://localhost/phpmyadmin`
2. Create database: `canteen_oltp`
3. Create database: `canteen_olap`
4. Import `database/canteen_oltp.sql` into `canteen_oltp`
5. Import `database/canteen_olap.sql` into `canteen_olap`

### Step 5 — Configure Database Connection
Open `config/db.php` and verify:
```php
Host:     localhost
Username: root
Password: (empty — XAMPP default)
```

### Step 6 — Create User Accounts
Visit:
```
http://localhost/cantech/setup_users.php
```
Then **delete** `setup_users.php` immediately after running it.

### Step 7 — Access the System
Open your browser and go to:
```
http://localhost/cantech/
```

### Step 8 — Run ETL
Log in as Admin → Click **Run ETL** → Click **Run ETL Now**

---

##  Default Accounts

| Role | Username | Password |
|---|---|---|
|  Admin | `admin` | `admin123` |
|  Cashier | `cashier1` | `cashier123` |

>  Change passwords immediately after first login via **User Management**.

---

##  Usage Guide

### Placing an Order (Cashier)
1. Click **Orders** → **+ New Order**
2. Search for customer or select **New Customer**
3. Select items with quantities (max 20 per item)
4. Choose payment method (Cash / GCash / Card)
5. If customer has 100+ loyalty points, check **Redeem Loyalty Points**
6. Click **Place Order**

### Running Analytics (Admin)
1. Click **Run ETL** → **Run ETL Now** to sync data
2. Click **Dashboard** to view charts
3. Click **OLAP Queries** for Roll-up, Drill-down, Slice, Dice
4. Click **Export Report** to download PDF or CSV

---

##  Database Design

### OLTP Database (canteen_oltp)
Fully normalized to **3NF** with 11 tables:

| Table | Purpose |
|---|---|
| `users` | System accounts with bcrypt passwords |
| `category` | Menu item categories |
| `menu_item` | Available food and drink items |
| `ingredient` | Raw ingredients with stock tracking |
| `menu_item_ingredient` | Ingredient mapping per menu item |
| `supplier` | Ingredient suppliers |
| `restock_log` | Ingredient restocking history |
| `customer` | Customers with loyalty points |
| `orders` | Order headers |
| `order_item` | Individual items per order |
| `payment` | Payment records |

### OLAP Database (canteen_olap) — Star Schema

**Fact Table:** `fact_sales`
- Measures: `quantity_sold`, `total_revenue`, `discount_amount`, `cost_of_goods`

**Dimension Tables:**
- `dim_time` — Year → Quarter → Month → Week → Day hierarchy
- `dim_menu_item` — Item names and pricing
- `dim_category` — Food categories
- `dim_customer` — Customer reference
- `dim_payment_method` — Cash, GCash, Card

---

##  OLAP Operations

| Operation | Description | SQL Technique |
|---|---|---|
| **Roll-up** | Aggregates daily sales to monthly/yearly totals | `GROUP BY d.year, d.month` |
| **Drill-down** | Breaks category totals down to item level | `GROUP BY c.category_name, m.item_name` |
| **Slice** | Filters one dimension (e.g., weekends only) | `WHERE d.is_weekend = 1` |
| **Dice** | Filters multiple dimensions simultaneously | `WHERE category + year + payment` |

---

##  Loyalty Points System

| Action | Points |
|---|---|
| ₱10 spent | +1 point |
| Order cancelled | Points reversed |
| Redeem 100 pts | ₱10 discount |
| 500+ pts | VIP tier status |

---

##  Team

| Member | Role |
|---|---|
| [Member 1] | Backend / Database Lead |
| [Member 2] | OLAP / ETL Developer |
| [Member 3] | Frontend / Dashboard |
| [Member 4] | Reports & Export |
| [Member 5] | Documentation & Security |

**Course:** BS Information Technology 201
**Subject:** DCIT 55B — Advanced Database System / ITEC 65A — Open-Source Technologies
**School:** Cavite State University — CvSU CCAT Campus
**Semester:** 2nd Semester, A.Y. 2025-2026

---

##  License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.
