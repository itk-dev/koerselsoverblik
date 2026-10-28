# 002: Configurable authentication modes

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Troels Ugilt Jensen |
| **Stakeholders** | ITK Dev developers; the transport office (users) |
| **Status** | Draft |

## Context

The first deliverable needs login with username and password. Single sign-on through the
identity provider (OIDC) comes later. Local development and demos benefit from running without a
login. Whatever the login method, every page must be guarded by a role.

### Drivers

- **Functional:** three ways to sign in (none, password, OIDC), the same role checks for all of
  them, and users created from the command line.
- **Non-functional:** security by default (deny unless granted), no accidental "no login" in
  production, and the ability to add OIDC without changing controllers.

### Options Considered

1. **One firewall with one authenticator per mode, selected by `APP_AUTH_MODE`**: each
   authenticator's `supports()` checks the mode. Authorization is independent of the mode.
2. **Separate firewall configurations per environment**: harder to test, and the mode is tied to
   the environment, not to configuration.
3. **Password only now, rework for OIDC later**: less work now, but it risks the role checks being
   built around the password user model.

## Decision

Option 1:

- **Authentication:** `APP_AUTH_MODE=none|password|oidc`, default `password`.
- **Authorization:**
  - Each part of the application has its own permission role, for example
    `ROLE_UPLOAD_DRIVING_DATA`. Controllers check only permission roles.
  - In `role_hierarchy`, `ROLE_ADMIN` gives all permission roles. At first, users get
    `ROLE_ADMIN` only.
  - Every controller has a class-level `#[IsGranted]`, and `access_control` denies by default.
  - A test fails if any route has no permission role, or if it checks `ROLE_ADMIN`.
- **`none` mode:** signs in a local user with `APP_NO_LOGIN_ROLES`. The application refuses to
  start in `prod` unless `APP_ALLOW_NO_LOGIN_IN_PROD=1`.

## Consequences

### Positive

- OIDC can be added as one more authenticator. The role checks do not change.
- Permission roles let an administrator give one user access to one part only, for example
  daily counts without upload. A new role bundle needs only a change to `role_hierarchy`.
- Access rules can be tested in `none` mode with different roles, without logging in.
- Deny by default, plus the route-guard test, makes a forgotten guard a failing test.

### Negative / Trade-offs

- Three authenticators to maintain and test.
- More roles to test: every route × every permission role.
- `none` mode is a risk if misconfigured. It is mitigated by the production guard.

### Follow-up Actions

- [ ] Permission roles and route-guard test (task M1-05).
- [ ] Password login and user commands (tasks M1-06, M1-07).
- [ ] Mode wiring and `none` mode (task M1-08).
