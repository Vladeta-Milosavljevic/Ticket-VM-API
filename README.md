# TicketVM API

JSON REST API for ticket management: users, roles, categories, tickets with workflow, comments, and file attachments. Built with Laravel and protected by [Laravel Sanctum](https://laravel.com/docs/sanctum) token authentication.

## Features

### Authentication

- `POST /api/login` — email and password; returns a bearer token and user payload.
- `POST /api/logout` — revokes the current access token (requires authentication).

### Users

- CRUD-style routes under `/api/users` (list, show, create, update, delete).
- Authorization: Only admins and managers can create users; new users are created as agents only (`StoreUserRequest`). Only admins can delete users.
- Soft deletes; restore with `POST /api/users/{user}/restore` (supports trashed users in the route).
- `GET /api/users/{user}/tickets` — tickets associated with the user.

### Roles and authorization

Users have one of: **`admin`**, **`manager`**, **`agent`**. Access to actions is enforced with Laravel policies (`TicketPolicy`, `UserPolicy`, `CategoryPolicy`, `AttachmentPolicy`).

### Tickets

- Standard resource routes: list, create, show, update, delete (`/api/tickets`).
- Workflow actions (on a single ticket):
    - `POST /api/tickets/{ticket}/assign`
    - `POST /api/tickets/{ticket}/complete`
    - `POST /api/tickets/{ticket}/approve`
    - `POST /api/tickets/{ticket}/reject`
- **Listing filters** (query parameters on `GET /api/tickets`): `category_id`, `manager_id`, `agent_id`, `status`, `urgency`, `unassigned`, `overdue`, `search`, `deadline_from`, `deadline_to`, `sort` (`created_at`, `deadline`, `urgency`, `status`), `order` (`asc`, `desc`).

### Ticket fields and workflow

- **Status** (for updates and filters): `open`, `in_progress`, `pending_review`, `completed`, `rejected`, `cancelled`.
- **Urgency**: `low`, `medium`, `high`, `critical`.
- Optional: `deadline`, `category_id`, `manager_id`, `agent_id`, plus completion and rejection timestamps/reason where applicable.

### Comments

- `GET /api/tickets/{ticket}/comments` — list comments.
- `POST /api/tickets/{ticket}/comments` — add a comment.
- Optional **is_internal** flag on comments (stored and returned in the API for clients or future visibility rules).

### Attachments

- Polymorphic attachments on tickets and comments.
- `DELETE /api/attachments/{attachment}` — remove an attachment.
- For **local development**, run `php artisan storage:link` so files on the `public` disk are served from `public/storage` (see [Laravel public disk](https://laravel.com/docs/filesystem#the-public-disk)).

### Categories

- CRUD under `/api/categories`.
- `POST /api/categories/{category}/archive` and `POST /api/categories/{category}/reactivate`.
- Unique `name` and `slug`; `is_archived` for soft retirement.

## Tech stack

- PHP **^8.2**
- Laravel **12**
- Laravel Sanctum **^4.2** (API tokens)
- [Pest](https://pestphp.com/) for tests
- Default local database in `.env.example` is **SQLite**; you can switch to MySQL or PostgreSQL via `DB_*` variables.

## Local setup

1. Clone the repository and install dependencies:

    ```bash
    composer install
    ```

2. Environment:

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

3. Database:

    ```bash
    php artisan migrate
    ```

4. **Storage (local development):** create the symlink for public file access (uploads / attachments):

    ```bash
    php artisan storage:link
    ```

5. **Optional demo data:**

    ```bash
    php artisan db:seed
    ```

    This creates sample users (1 admin, 3 managers, 6 agents), categories, tickets, comments, and attachments. Seeded users use the factory default password: **`password`**. Admin user: **`admin@example.com`**.

## API usage

- **Base URL:** paths are prefixed with `/api` (e.g. `https://your-app.test/api/login`).
- **Authenticated requests:** send `Authorization: Bearer {token}` using the `token` value from the login response.

### Route overview

| Method    | Path                                    | Description                     |
| --------- | --------------------------------------- | ------------------------------- |
| POST      | `/api/login`                            | Authenticate; returns token     |
| POST      | `/api/logout`                           | Revoke current token            |
| GET       | `/api/categories`                       | List categories                 |
| POST      | `/api/categories`                       | Create category                 |
| GET       | `/api/categories/{category}`            | Show category                   |
| PUT       | `/api/categories/{category}`            | Update category                 |
| POST      | `/api/categories/{category}/archive`    | Archive category                |
| POST      | `/api/categories/{category}/reactivate` | Reactivate category             |
| GET       | `/api/tickets`                          | List tickets (supports filters) |
| POST      | `/api/tickets`                          | Create ticket                   |
| GET       | `/api/tickets/{ticket}`                 | Show ticket                     |
| PUT/PATCH | `/api/tickets/{ticket}`                 | Update ticket                   |
| DELETE    | `/api/tickets/{ticket}`                 | Delete ticket                   |
| POST      | `/api/tickets/{ticket}/assign`          | Assign ticket                   |
| POST      | `/api/tickets/{ticket}/complete`        | Complete ticket                 |
| POST      | `/api/tickets/{ticket}/approve`         | Approve ticket                  |
| POST      | `/api/tickets/{ticket}/reject`          | Reject ticket                   |
| GET       | `/api/tickets/{ticket}/comments`        | List comments                   |
| POST      | `/api/tickets/{ticket}/comments`        | Create comment                  |
| DELETE    | `/api/attachments/{attachment}`         | Delete attachment               |
| GET       | `/api/users`                            | List users                      |
| POST      | `/api/users`                            | Create user                     |
| GET       | `/api/users/{user}`                     | Show user                       |
| PUT/PATCH | `/api/users/{user}`                     | Update user                     |
| DELETE    | `/api/users/{user}`                     | Delete user                     |
| GET       | `/api/users/{user}/tickets`             | User’s tickets                  |
| POST      | `/api/users/{user}/restore`             | Restore soft-deleted user       |

For the exact list on your install:

```bash
php artisan route:list --path=api
```

## Testing

```bash
php artisan test
```

Feature tests cover HTTP endpoints, validation, authentication, and authorization.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
