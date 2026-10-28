# 002: Configurable authentication modes

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Troels Ugilt Jensen |
| **Stakeholders** | ITK Dev developers; the transport office (users) |
| **Status** | Draft |

## Context

The first deliverable uses single sign-on through the identity provider (OIDC). Password login
was planned first, but it is dropped. Local development and demos benefit from running without a
login. Whatever the login method, every page must be guarded by a role.

### Drivers

- **Functional:** two ways to sign in (none, OIDC), and the same role checks for both of them.
- **Non-functional:** security by default (deny unless granted), no accidental "no login" in
  production, and the ability to add a login method without changing controllers.

### Options Considered

1. **One firewall with one authenticator per mode, selected by `APP_AUTH_MODE`**: each
   authenticator's `supports()` checks the mode. Authorization is independent of the mode.
2. **Separate firewall configurations per environment**: harder to test, and the mode is tied to
   the environment, not to configuration.
3. **OIDC only, without `none` mode**: one authenticator less. Local development has a mock
   identity provider, but access tests per role and demos are easier without a login.

## Decision

Option 1:

- **Authentication:** `APP_AUTH_MODE=oidc|none`, default `oidc`.
- **Authorization:**
  - Each part of the application has its own permission role, for example
    `ROLE_UPLOAD_DRIVING_DATA`. Controllers check only permission roles.
  - In `role_hierarchy`, `ROLE_ADMIN` gives all permission roles.
  - The identity provider (Azure B2C) is authoritative for roles. At first there is one role:
    `admin` in the `roles` claim gives `ROLE_ADMIN`. At each sign-in, the roles from the token
    replace the stored roles. A user without the admin role cannot sign in. Neither the
    environment nor the application changes roles.
  - Every controller has a class-level `#[IsGranted]`, and `access_control` denies by default.
  - A test fails if any route has no permission role, or if it checks `ROLE_ADMIN`.
- **`none` mode:** signs in a local user with `APP_NO_LOGIN_ROLES`. The application refuses to
  start in `prod` unless `APP_ALLOW_NO_LOGIN_IN_PROD=1`.

## Consequences

### Positive

- A new login method is one more authenticator. The role checks do not change.
- No passwords, user commands or user administration in the application.
- Permission roles let the identity provider give one group access to one part only, for
  example daily counts without upload. A new role bundle needs only a change to `role_hierarchy`.
- Access rules can be tested in `none` mode with different roles, without logging in.
- Deny by default, plus the route-guard test, makes a forgotten guard a failing test.

### Negative / Trade-offs

- Two authenticators to maintain and test.
- A role removed in the identity provider takes effect at the next sign-in, not in a running
  session.
- At first, all users have the same access. Narrower access needs more role values in the token,
  and code that maps them to permission roles.
- More roles to test: every route × every permission role.
- `none` mode is a risk if misconfigured. It is mitigated by the production guard.

### Follow-up Actions

- [ ] Permission roles and route-guard test (task M1-05).
- [ ] User entity and OIDC login (tasks M1-06, M1-07).
- [ ] Mode wiring and `none` mode (task M1-08).
