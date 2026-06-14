# Database Files

This folder contains the SQL scripts required to initialize the CanTech system databases.

## Files

| File | Database | Description |
|---|---|---|
| `canteen_oltp.sql` | canteen_oltp | Full OLTP schema with all 11 tables and seed data |
| `canteen_olap.sql` | canteen_olap | Star Schema with fact and dimension tables |

## Setup Instructions

1. Open phpMyAdmin at `http://localhost/phpmyadmin`
2. Create `canteen_oltp` database and import `canteen_oltp.sql`
3. Create `canteen_olap` database and import `canteen_olap.sql`
4. Run ETL from the admin panel to populate the OLAP database
