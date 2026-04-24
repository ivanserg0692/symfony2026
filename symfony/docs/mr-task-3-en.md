# MR Task 3 Result Log

## Overview

This document captures the visible result of the current merge request related to the admin area and authentication flow.

The work shown here covers:
- EasyAdmin 5 installation
- admin login form setup
- admin dashboard availability
- user list management in EasyAdmin
- filtering users by groups
- JWT authentication endpoints in Swagger UI

## Screenshots

### EasyAdmin 5 Installed

![EasyAdmin 5 installed](images/easyadmin-success.png)

The screenshot shows the default EasyAdmin 5 page after the bundle installation was completed successfully.

### Admin Login Form

![Admin login form](images/login-form.png)

The login page is available for the admin area and includes the email field, password field, and Cloudflare Turnstile verification.

### Admin Dashboard

![EasyAdmin dashboard](images/easy-admin-dashboard.png)

The admin dashboard is accessible after successful authentication and provides the initial workspace entry point for further admin features.

### User List

![User list in EasyAdmin](images/user-list.png)

The user list page is available in EasyAdmin and shows the configured columns for identifiers, email, names, and roles together with search and filtering controls.

### User Group Filter

![User group filter in EasyAdmin](images/user-group-filter.png)

The user list now includes a group filter. This makes it possible to quickly narrow the list to administrative and other working groups directly in the EasyAdmin interface.

### Filtered User List

![Filtered user list in EasyAdmin](images/filtered-user-list.png)

The screenshot shows the restricted user list for a regular user. In this mode the list only exposes the current user account, which matches the visibility rules and ensures that editing is limited to the user's own profile.

### JWT Auth Endpoints

![JWT auth endpoints in Swagger](images/jwt-auth-endpoints.png)

Swagger UI exposes the main JWT authentication endpoints for login, refresh, logout, and current user retrieval.

## Updates for 2026-04-23

- password change was added to the admin user form
- the new password is hashed before saving and is never stored in plain text
- the `User::isAdmin()` logic was updated to recognize both the `admin` group and groups with the `isAdmin = true` flag
- `NewsVoter` was added to centralize API access checks for viewing news items
- `NewsVoter` evaluates both the news status and the current user: `public` news is available to everyone, `internal` news is available to authenticated users, and non-public news is also available to administrators and the news author
- when access is denied, the API hides the item and returns `404`

## Updates for 2026-04-24

- user access rules were aligned across the admin area and the API so regular users can safely access the related sections and endpoints and only receive data they are allowed to see
- `UsersVoter` now allows administrators to view and edit any user, while non-admin users can only view and edit their own profile
- the admin user edit page now explicitly checks the voter before opening the form
- access to user groups is now centralized through `UserGroupsVoter` and is restricted to administrators only
- the `Groups` menu item in EasyAdmin is hidden when the current user has no access to the groups index page
- `UserRepository` and `NewsRepository` visibility methods were cleaned up and made more consistent around root alias handling and visibility filtering
