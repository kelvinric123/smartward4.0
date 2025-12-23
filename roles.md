# User Roles and Permissions

This document outlines the user roles available in the system and their respective permissions.

## Roles

### 1. Superadmin
-   **Creation**: Only seeded by seeder (cannot be created via UI).
-   **Access**: Full access to all features.

### 2. Hospital Admin
-   **Creation**: Can be added by Superadmin (and likely other Hospital Admins?) in the Users Management page.
-   **Access**: Same access permissions as Superadmin.

### 3. Nurse Head
-   **Creation**: Can be managed in Users Management.
-   **Access**: 
    -   Generally like Hospital Admin, BUT:
    -   **Integration**: Access allowed.
    -   **Admin Management**: Can ONLY see "Nurses" subsection.

### 4. Ward Dashboard Login
-   **Creation**: Can be managed in Users Management.
-   **Access**: Can ONLY see the Ward Dashboard.

### 5. IT Admin
-   **Description**: (To be defined).
