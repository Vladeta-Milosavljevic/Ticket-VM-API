# TicketVM API - Postman Collection

This document provides instructions for using the TicketVM API Postman collection.

## 📋 Table of Contents

-   [Importing the Collection](#importing-the-collection)
-   [Configuration](#configuration)
-   [Authentication](#authentication)
-   [Endpoints Overview](#endpoints-overview)
-   [Request Examples](#request-examples)
-   [Response Formats](#response-formats)
-   [Error Handling](#error-handling)
-   [Workflow Guide](#workflow-guide)

## 📥 Importing the Collection

1. Open Postman
2. Click **Import** in the top left corner
3. Select **File** tab
4. Import these files from the `api-requests/` folder (you can select all three at once):
    - `TicketVM-API.postman_collection.json`
    - `TicketVM-Local.postman_environment.json`
    - `TicketVM-Production.postman_environment.json`
5. Click **Import**

The collection will appear with folders for each resource. The two **environments** appear under **Environments** in the sidebar.

## ⚙️ Configuration

### Choosing Local vs Production

Use Postman’s **environment** dropdown (top right) to pick which base URL applies:

| Environment            | `base_url` value |
| ---------------------- | ---------------- |
| **TicketVM - Local**   | `http://127.0.0.1:8000` (or use your Herd URL if different) |
| **TicketVM - Production** | `https://ticket-vm-api-main-narkga.free.laravel.cloud` |

When an environment is active, its variables **override** the collection’s default `base_url`. If you import the collection only and select **No Environment**, defaults stay at `http://127.0.0.1:8000` (safer than pointing at production by mistake).

**Caution:** With **TicketVM - Production** selected, mutating requests (updates, deletes, etc.) run against your **live** API and database. Double-check the environment before sending destructive calls.

### Base URLs and variables

The collection uses:

-   `{{base_url}}` — API host (scheme + host, **no** trailing slash). Default on the collection: `http://127.0.0.1:8000`.
-   `{{app_url}}` — Same host as `base_url` in the bundled environments (reserved for consistency with collection variables).
-   `{{bearer_token}}` — Automatically populated after **Login** (stores the authentication token).

**Note:** All API endpoints include `/api` in the path (e.g., `{{base_url}}/api/login`).

### Changing the base URL manually

1. Click the collection name → **Variables** tab, or edit the active environment’s variables.
2. Update `base_url` (and `app_url` if you use it).
3. Click **Save**.

If your production hostname on Laravel Cloud changes, update `TicketVM-Production.postman_environment.json` (or the **TicketVM - Production** environment in Postman after import) and re-export if you version-control the file.

## 🔐 Authentication

This API uses **bearer token authentication** via Laravel Sanctum. The authentication workflow:

1. **Login** using the `/api/login` endpoint with your credentials
2. The bearer token is automatically extracted from the response and saved to the `{{bearer_token}}` collection variable
3. All subsequent authenticated requests automatically include the token in the `Authorization` header
4. Use **Logout** to revoke the token

### Important Notes

-   **Automatic Token Management**: The collection includes a test script on the Login request that automatically saves the bearer token to the collection variable. You don't need to manually copy/paste tokens.
-   **Token Persistence**: The token persists in the collection variable for your Postman session until you logout or close Postman.
-   **Authorization Header**: All authenticated requests automatically include `Authorization: Bearer {{bearer_token}}` header.
-   **Token Revocation**: Calling the Logout endpoint revokes the current token on the server, making it invalid for future requests.

### Authentication Examples

**Step 1: Login**

```json
POST /api/login
{
    "email": "admin@example.com",
    "password": "password"
}
```

**Response:**

```json
{
    "message": "Login successful",
    "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "admin",
        ...
    }
}
```

The `token` field contains the bearer token that is automatically saved to the `{{bearer_token}}` collection variable.

**Step 2: Making Authenticated Requests**

After login, all authenticated requests automatically include the token:

```http
GET /api/tickets
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

You don't need to manually add this header - it's automatically included via the `{{bearer_token}}` variable.

**Step 3: Logout**

```http
POST /api/logout
Authorization: Bearer {{bearer_token}}
```

This revokes the token on the server. You'll need to login again to get a new token.

## 📚 Endpoints Overview

### Updates (Postman collection)

The API defines updates as **`PUT`** routes. This Postman collection uses **HTTP `POST`** with **`_method=PUT`** (Laravel method spoofing) so **`multipart/form-data`** and file uploads behave reliably and all updates follow the same pattern. For JSON updates, include `"_method": "PUT"` in the body; for **Update Ticket**, add a form field `_method=PUT`. Direct **`PUT`** requests still work (e.g. JSON-only clients).

### Authentication

-   `POST /api/login` - Authenticate user and receive bearer token
-   `POST /api/logout` - Logout user and revoke bearer token

### Tickets

-   `GET /api/tickets` - List all tickets (paginated)
-   `POST /api/tickets` - Create a new ticket
-   `GET /api/tickets/{id}` - Get a specific ticket
-   `PUT /api/tickets/{id}` - Update a ticket (collection uses `POST` + `_method=PUT`; see [Updates (Postman collection)](#updates-postman-collection))
-   `DELETE /api/tickets/{id}` - Delete a ticket
-   `POST /api/tickets/{id}/assign` - Assign an agent to a ticket
-   `POST /api/tickets/{id}/complete` - Mark ticket as completed
-   `POST /api/tickets/{id}/approve` - Approve ticket completion
-   `POST /api/tickets/{id}/reject` - Reject ticket completion
-   `GET /api/tickets/{id}/comments` - Get ticket comments
-   `POST /api/tickets/{id}/comments` - Add a comment to a ticket

### Users

-   `GET /api/users` - List all users (paginated)
-   `POST /api/users` - Create a new user
-   `GET /api/users/{id}` - Get a specific user
-   `PUT /api/users/{id}` - Update a user (collection uses `POST` + JSON `_method`; see [Updates (Postman collection)](#updates-postman-collection))
-   `DELETE /api/users/{id}` - Delete a user (soft delete)
-   `POST /api/users/{id}/restore` - Restore a soft-deleted user (admin only)
-   `GET /api/users/{id}/tickets` - Get tickets for a user: if the user is a **manager**, tickets they **manage**; otherwise (e.g. **agent**), tickets **assigned** to them as agent

## 📝 Request Examples

### Create Ticket

Use **multipart/form-data** for Create Ticket (supports optional file attachments).

**Field Requirements:**

-   `title` (required): String, max 255 characters
-   `description` (required): String
-   `urgency` (required): One of `low`, `medium`, `high`, `critical`
-   `deadline` (required): ISO 8601 date, must be in the future
-   `category_id` (optional): Integer, must exist in categories table
-   `manager_id` (required for non-managers, optional for managers/admins): Integer, must exist in users table
-   `agent_id` (optional, only admins/managers can set): Integer, must exist in users table
-   `attachments` (optional): Up to 5 files, 10MB each. Allowed: jpeg, png, gif, pdf, doc, docx, txt

### Update Ticket

Use **multipart/form-data** for Update Ticket (supports optional file attachments). Send **HTTP POST** with form field **`_method=PUT`** so uploads are handled correctly; the route remains **`PUT /api/tickets/{id}`**.

All fields are optional. Only include fields you want to update. Optional `attachments`: max 5 files, 10MB each.

**Status Values:**

-   `open` - Ticket is open
-   `in_progress` - Work is in progress
-   `pending_review` - Awaiting manager approval
-   `completed` - Ticket is completed
-   `rejected` - Completion was rejected
-   `cancelled` - Ticket was cancelled

### Assign Agent

```json
POST /api/tickets/1/assign
{
    "agent_id": 2
}
```

**Authorization:** Only ticket manager or admins can assign agents.

### Complete Ticket

```json
POST /api/tickets/1/complete
```

No body required. Changes status to `pending_review`.

**Authorization:** Only assigned agent or admins can complete tickets.

### Reject Ticket

```json
POST /api/tickets/1/reject
{
    "rejection_reason": "Work does not meet quality standards. Please revise."
}
```

**Authorization:** Only ticket manager or admins can reject. Ticket must be in `pending_review` status.

### Create Comment

Use **multipart/form-data** for Create Comment (supports optional file attachments).

-   `body` (required): String, max 255 characters
-   `is_internal` (optional): Boolean, defaults to false
-   `attachments` (optional): Up to 5 files, 10MB each. Allowed: jpeg, png, gif, pdf, doc, docx, txt
-   `is_internal` (optional): Boolean, defaults to `false`

### Create User

```json
POST /api/users
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "role": "agent"
}
```

**Role Values:**

-   `admin` - Full access
-   `manager` - Can manage tickets and users
-   `agent` - Can work on assigned tickets

**Authorization:** Only admins and managers can create users.

### Delete Attachment

```http
DELETE /api/attachments/:id
```

**Authorization:** Only ticket manager, assigned agent, or admins can delete attachments. The file is removed from storage.

### File Attachments

-   **Create Ticket**, **Update Ticket**, and **Create Comment** accept optional `attachments` (multipart/form-data).
-   Max 5 files per request, 10MB each. Allowed types: jpeg, png, gif, pdf, doc, docx, txt.
-   The `url` field in attachment responses depends on storage: **S3** returns a **temporary signed URL** (~60 minutes). The **public** disk (typical local dev) returns a regular public URL—run `php artisan storage:link` so `/storage` paths resolve. There is no separate download route.

## 📦 Response Formats

### Success Response

Most endpoints return JSON with the requested resource:

```json
{
    "data": {
        "id": 1,
        "title": "Ticket Title",
        "description": "Description",
        ...
    }
}
```

### Paginated Response

List endpoints return paginated data:

```json
{
    "data": [...],
    "links": {
        "first": "http://127.0.0.1:8000/api/tickets?page=1",
        "last": "http://127.0.0.1:8000/api/tickets?page=5",
        "prev": null,
        "next": "http://127.0.0.1:8000/api/tickets?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "path": "http://127.0.0.1:8000/api/tickets",
        "per_page": 15,
        "to": 15,
        "total": 75
    }
}
```

### Error Response

Error responses follow this format:

```json
{
    "message": "Error message here"
}
```

Validation errors:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password must be at least 8 characters."]
    }
}
```

## ⚠️ Error Handling

### Common HTTP Status Codes

-   `200 OK` - Request successful
-   `201 Created` - Resource created successfully
-   `400 Bad Request` - Validation error or invalid request
-   `401 Unauthorized` - Authentication required or invalid credentials
-   `403 Forbidden` - Insufficient permissions
-   `404 Not Found` - Resource not found
-   `422 Unprocessable Entity` - Validation failed
-   `500 Internal Server Error` - Server error

### Authorization Errors

Many endpoints have role-based authorization:

-   **Ticket Assignment**: Only ticket manager or admins
-   **Ticket Completion**: Only assigned agent or admins
-   **Ticket Approval/Rejection**: Only ticket manager or admins
-   **Comments**: Only ticket manager, assigned agent, or admins
-   **User Creation**: Only admins and managers
-   **User Deletion**: Only admins
-   **Ticket Deletion**: Only admins and managers

## 🔄 Workflow Guide

### Complete Ticket Workflow

1. **Create Ticket** (`POST /api/tickets`)

    - Any authenticated user can create tickets
    - Managers/admins can optionally assign agent during creation

2. **Assign Agent** (`POST /api/tickets/{id}/assign`)

    - Manager or admin assigns an agent to work on the ticket
    - Required before agent can complete the ticket

3. **Work on Ticket**

    - Agent can update ticket details (`PUT /api/tickets/{id}`; Postman collection: `POST` + `_method=PUT`)
    - Agent can add comments (`POST /api/tickets/{id}/comments`)

4. **Complete Ticket** (`POST /api/tickets/{id}/complete`)

    - Agent marks ticket as complete
    - Status changes to `pending_review`

5. **Review Ticket**
    - Manager reviews the completed work
    - Two options:
        - **Approve** (`POST /api/tickets/{id}/approve`) - Status becomes `completed`
        - **Reject** (`POST /api/tickets/{id}/reject`) - Status resets to `in_progress`, agent must revise

### User Management Workflow

1. **List Users** (`GET /api/users`) - View all users
2. **Create User** (`POST /api/users`) - Only admins/managers
3. **Update User** (`PUT /api/users/{id}`; Postman collection: `POST` + JSON `_method`) - Only admins/managers
4. **View User Tickets** (`GET /api/users/{id}/tickets`) - For a manager, tickets they manage; for an agent, tickets assigned to them
5. **Delete User** (`DELETE /api/users/{id}`) - Only admins (soft delete)
6. **Restore User** (`POST /api/users/{id}/restore`) - Only admins; use the soft-deleted user's id

## 💡 Tips & Best Practices

1. **Login First**: Always start your testing session by calling the Login endpoint to get a bearer token
2. **Token Management**: The bearer token is automatically saved after login - you don't need to manually copy it
3. **Use Variables**: Select **TicketVM - Local** or **TicketVM - Production** (or edit `{{base_url}}` on the collection / environment)
4. **Check Authorization**: Ensure your test user has the correct role for the endpoint
5. **Handle Pagination**: Use the `page` query parameter for list endpoints
6. **Validate Responses**: Check response status codes and error messages
7. **Test Error Cases**: Try invalid data, unauthorized access, and missing fields
8. **Token Expiration**: If you get a 401 Unauthorized error, your token may have expired - login again to get a new token
9. **Clean Up**: Delete test data after testing or use a test database

## 🔍 Testing Checklist

-   [ ] Login with valid credentials
-   [ ] Verify bearer token is automatically saved to collection variable
-   [ ] Login with invalid credentials (should fail)
-   [ ] Access protected endpoint without bearer token (should fail with 401)
-   [ ] Access protected endpoint with invalid token (should fail with 401)
-   [ ] Create ticket with all required fields
-   [ ] Create ticket with missing required fields (should fail)
-   [ ] Update ticket with valid data
-   [ ] Assign agent to ticket
-   [ ] Complete ticket as assigned agent
-   [ ] Approve ticket as manager
-   [ ] Reject ticket as manager
-   [ ] Add comment to ticket
-   [ ] View ticket comments
-   [ ] Create user as admin/manager
-   [ ] Create user as agent (should fail)
-   [ ] Delete user as admin
-   [ ] Delete user as non-admin (should fail)
-   [ ] Logout successfully

## 📞 Support

For issues or questions:

-   Check Laravel logs: `storage/logs/laravel.log`
-   Review API documentation in code comments
-   Check validation rules in Form Request classes

---

**Last Updated:** March 28, 2026

**Authentication Method:** Bearer Token (Laravel Sanctum)
