# 002: OIDC authentication with roles from the identity provider

| Field | Value |
| --- | --- |
| **Created By** | Troels Ugilt Jensen |
| **Date** | 2026-10-10 |
| **Decision Maker** | Troels Ugilt Jensen |
| **Stakeholders** | ITK Dev developers; the transport office (users) |
| **Status** | Draft |

## Context

The users sign in with single sign-on through the identity provider (OIDC). Local development
needs a login that works without the real identity provider. Whatever the login method, every
page must be guarded by a role.

An earlier draft of this ADR had three modes (`none`, `password` and `oidc`), selected by
`APP_AUTH_MODE`. That draft is replaced: OIDC is the only login method, from milestone 1.

### Drivers

- **Functional:** single sign-on, roles that the identity provider controls, and a local login
  for development.
- **Non-functional:** security by default (deny unless granted), no passwords in the
  application, multi-factor authentication from the identity provider, and few code paths to
  maintain and test.

### Options Considered

1. **OIDC only, with `itk-dev/openid-connect-bundle`**: one authenticator. Local development
   uses an OIDC mock service in Docker.
2. **OIDC and password login**: the application must store passwords, apply a password policy
   and throttle login attempts. Two authenticators to maintain.
3. **Configurable modes (`none`, `password`, `oidc`)**: the earlier draft. Three authenticators,
   and a `none` mode that is a risk if it is enabled in production.

## Decision

Option 1:

- **Authentication:**
  - `OidcAuthenticator` extends the bundle's `OpenIdLoginAuthenticator`. There is one firewall.
  - Anonymous users are sent to the identity provider. The bundle routes under `/openidconnect`,
    the callback `/openid-connect/generic` and the assets are public.
  - The settings are `OIDC_METADATA_URL`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`,
    `OIDC_REDIRECT_URI` and `OIDC_ALLOW_HTTP`.
- **Users:** the `User` entity is created at the first login and updated at each login. It keeps
  the email (the `upn` claim), the roles and the time of the last login. It has no password.
  There is no user list in the application.
- **Authentication log:** the bundle's audit trail records each successful and failed login.
  `OidcLogin` adds a record with the email, the roles and the time of each login. Both go
  to the `openid_connect_audit` channel and its own log file.
- **Roles from a claim:**
  - The identity provider sends the roles in the claim `OIDC_ROLE_CLAIM`.
  - `OidcRoleMapper` keeps the values that are `ROLE_ADMIN` or a permission role, and ignores
    the rest.
  - The application does not edit roles. A role change applies at the next login.
- **Authorization:**
  - Each part of the application has its own permission role, for example
    `ROLE_UPLOAD_DRIVING_DATA`. Controllers check only permission roles.
  - In `role_hierarchy`, `ROLE_ADMIN` gives all permission roles. At first, users get
    `ROLE_ADMIN` only.
  - Every controller has a class-level `#[IsGranted]`, and `access_control` denies by default.
  - A test fails if any route has no permission role, or if it checks `ROLE_ADMIN`.
- **Local development:** the `idp` service in `docker-compose.override.yaml` runs an OIDC mock
  (`ghcr.io/geigerzaehler/oidc-provider-mock`). It has one test user with `ROLE_ADMIN` and one
  test user for each permission role.
- **Tests:** functional tests sign in with `loginUser()`. The tests of the OIDC flow replace the
  identity provider with a stub.

## Consequences

### Positive

- One authenticator to maintain and test.
- The application stores no passwords. There is no password policy, password reset or login
  throttling to build.
- The identity provider gives multi-factor authentication.
- Permission roles let the identity provider give one user access to one part only, for example
  daily counts without upload. A new role bundle needs only a change to `role_hierarchy`.
- Deny by default, plus the route-guard test, makes a forgotten guard a failing test.

### Negative / Trade-offs

- Local development needs the mock service.
- The application depends on the identity provider. If it is not available, nobody can sign in.
- Roles are managed outside the application. IT operations must set up the role claim
  (name and values) before go-live.
- A role change applies only at the next login. A user who is signed in keeps the old roles
  until the session ends.
- The authentication log holds personal data (emails and IP addresses). Its retention follows
  the staff data decision in ADR 004.

### Follow-up Actions

- [ ] Permission roles and route-guard test (task M1-05).
- [ ] `User` entity and role mapping (task M1-06).
- [ ] OIDC login and the mock service (task M1-07).
- [ ] Agree the role claim (name and values) with IT operations before go-live (task M1-28).
