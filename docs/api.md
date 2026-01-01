# API Reference

Bulla provides a REST API for the embed widget and external integrations.

## Base URL

All API endpoints are prefixed with `/api`.

## Authentication

Public endpoints require no authentication. Admin endpoints require session authentication.

## Endpoints

### Get Comments

Retrieve comments for a thread.

```
GET /api/threads/{uri}/comments
```

**Parameters:**
- `uri` (path) - URL-encoded page path
- `sort` (query, optional) - Sort order: `oldest`, `newest`, `popular` (default: `oldest`)

**Response:**
```json
{
  "thread": {
    "id": 1,
    "uri": "/blog/post-1",
    "title": "My Blog Post"
  },
  "total": 5,
  "comments": [
    {
      "id": 1,
      "parent_id": null,
      "parent_author": null,
      "depth": 0,
      "author": "John Doe",
      "is_admin": false,
      "is_github_user": false,
      "github_username": null,
      "avatar": "https://gravatar.com/avatar/...",
      "website": "https://example.com",
      "body_html": "<p>Comment content</p>",
      "upvotes": 3,
      "downvotes": 0,
      "created_at": "2025-01-15T10:30:00Z",
      "replies": []
    }
  ]
}
```

### Create Comment

Create a new comment.

```
POST /api/threads/{uri}/comments
```

**Body:**
```json
{
  "body": "Comment in **markdown**",
  "author": "Jane Doe",
  "email": "jane@example.com",
  "website": "https://jane.example.com",
  "parent_id": null,
  "notify_replies": true,
  "title": "Page Title",
  "url": "https://example.com/page",
  "timestamp": "signed_timestamp_from_config"
}
```

**Required:** `body`

**Response (201):**
```json
{
  "id": 2,
  "author": "Jane Doe",
  "is_admin": false,
  "is_github_user": false,
  "github_username": null,
  "avatar": "https://gravatar.com/avatar/...",
  "website": "https://jane.example.com",
  "body_html": "<p>Comment in <strong>markdown</strong></p>",
  "status": "approved",
  "upvotes": 0,
  "downvotes": 0,
  "created_at": "2025-01-15T11:00:00Z",
  "edit_token": "random_64_char_token",
  "edit_token_expires_at": "2025-01-15T11:15:00Z"
}
```

### Get Comment

Retrieve a single comment.

```
GET /api/comments/{id}
```

**Response:**
```json
{
  "id": 1,
  "author": "John Doe",
  "is_admin": false,
  "is_github_user": false,
  "github_username": null,
  "avatar": "https://gravatar.com/avatar/...",
  "website": "https://example.com",
  "body_html": "<p>Comment content</p>",
  "body_markdown": "Comment content",
  "status": "approved",
  "upvotes": 3,
  "downvotes": 0,
  "created_at": "2025-01-15T10:30:00Z"
}
```

### Update Comment

Update a comment within the edit window.

```
PUT /api/comments/{id}
```

**Body:**
```json
{
  "body": "Updated comment",
  "author": "New Name",
  "website": "https://example.com",
  "edit_token": "token_from_create_response"
}
```

**Required:** `edit_token`

**Response:**
```json
{
  "id": 1,
  "author": "New Name",
  "website": "https://example.com",
  "body_html": "<p>Updated comment</p>",
  "body_markdown": "Updated comment"
}
```

### Delete Comment

Delete a comment within the edit window.

```
DELETE /api/comments/{id}
```

**Body:**
```json
{
  "edit_token": "token_from_create_response"
}
```

**Response:**
```json
{
  "deleted": true
}
```

### Upvote Comment

Upvote a comment (one per IP).

```
POST /api/comments/{id}/upvote
```

**Response:**
```json
{
  "upvotes": 4
}
```

**Error (409 - Already voted):**
```json
{
  "error": "Already voted."
}
```

### Downvote Comment

Downvote a comment (one per IP). Only available when downvotes are enabled.

```
POST /api/comments/{id}/downvote
```

**Response:**
```json
{
  "downvotes": 1
}
```

**Error (409 - Already voted):**
```json
{
  "error": "Already voted."
}
```

### Preview Markdown

Preview markdown rendering.

```
POST /api/comments/preview
```

**Body:**
```json
{
  "body": "**bold** and *italic*"
}
```

**Response:**
```json
{
  "html": "<p><strong>bold</strong> and <em>italic</em></p>"
}
```

### Get Comment Counts

Get comment counts for multiple pages.

```
POST /api/counts
```

**Body:**
```json
{
  "uris": ["/blog/post-1", "/blog/post-2"]
}
```

**Response:**
```json
{
  "/blog/post-1": 5,
  "/blog/post-2": 12
}
```

### Get Config

Get public configuration and a signed timestamp for spam protection.

```
GET /api/config
```

**Response:**
```json
{
  "site_name": "My Blog",
  "require_author": false,
  "require_email": false,
  "moderation_mode": "none",
  "max_depth": 3,
  "edit_window_minutes": 15,
  "timestamp": "signed_timestamp_string",
  "is_admin": false,
  "enable_upvotes": true,
  "enable_downvotes": false,
  "admin_badge_label": "Author",
  "accent_color": "#3b82f6",
  "github_auth_enabled": false,
  "commenter": null,
  "hide_branding": false
}
```

**Fields:**
- `site_name` - Site name displayed in the widget
- `require_author` - Whether author name is required
- `require_email` - Whether email is required
- `moderation_mode` - Comment moderation: `none`, `new_commenters`, or `all`
- `max_depth` - Visual nesting depth (0-3). Replies are always allowed at any depth.
- `edit_window_minutes` - Minutes allowed for editing after posting
- `timestamp` - Signed timestamp for spam protection (pass to create comment)
- `is_admin` - Whether current session is admin authenticated
- `enable_upvotes` - Whether upvoting is enabled
- `enable_downvotes` - Whether downvoting is enabled
- `admin_badge_label` - Label for admin badge (e.g., "Author", "Admin")
- `accent_color` - Theme accent color (hex)
- `github_auth_enabled` - Whether GitHub authentication is available
- `commenter` - Current GitHub-authenticated user info, or null
- `hide_branding` - Whether to hide Bulla branding

## Error Responses

### Validation Error (422)

```json
{
  "message": "The body field is required.",
  "errors": {
    "body": ["The body field is required."]
  }
}
```

### Forbidden (403)

```json
{
  "error": "Invalid or expired edit token."
}
```

### Rate Limited (429)

```json
{
  "message": "Too many requests. Please try again later."
}
```

### Spam Detected (422)

```json
{
  "error": "Your comment was flagged as spam."
}
```

## Atom Feeds

### Recent Comments

```
GET /feed/recent.atom
```

Returns the 50 most recent approved comments across all threads.

### Thread Comments

```
GET /feed/{uri}.atom
```

Returns the 50 most recent approved comments for a specific thread.

**Example:** `/feed/blog/my-post.atom`

## Webhooks

Bulla does not currently support webhooks. Use Atom feeds for syndication.

## Rate Limits

- **Comment creation:** 5 per minute per IP (configurable)
- **Other endpoints:** No rate limits

## CORS

All API endpoints support CORS. Configure allowed origins in Admin > Settings.
